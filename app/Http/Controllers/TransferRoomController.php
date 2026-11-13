<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Transfer\CloseTransferRoom;
use App\Actions\Transfer\CreateTransferRoom;
use App\Actions\Transfer\DeclineNearbyInvite;
use App\Actions\Transfer\InviteNearbyDevice;
use App\Actions\Transfer\IssueTurnCredentials;
use App\Actions\Transfer\JoinTransferRoom;
use App\Actions\Transfer\PostTransferSignal;
use App\Actions\Transfer\PullTransferSignals;
use App\Actions\Transfer\SyncNearbyPresence;
use App\Actions\Transfer\TurnQuota;
use App\Domain\NetworkKey;
use App\Domain\RoomCode;
use App\Enums\TransferRole;
use App\Exceptions\TransferRoomException;
use App\Models\TransferRoom;
use App\Models\TransferSignal;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * API mai mối cho gửi file trực tiếp (/gui-file). Không cần đăng nhập, không dùng session: mỗi máy
 * gửi token phòng trong header X-Room-Token. Server chỉ chuyển tin nhắn bắt tay WebRTC, không bao
 * giờ thấy tên hay nội dung file, và không ghi log nội dung tin nhắn.
 */
final class TransferRoomController
{
    /** Nhập sai mã tối đa ngần này lần mỗi 10 phút cho một IP. */
    public const MAX_FAILED_JOINS = 10;

    /** Một tin nhắn bắt tay không quá 64 KB (offer/answer thường dưới 10 KB). */
    public const MAX_SIGNAL_BYTES = 65536;

    public function store(CreateTransferRoom $createRoom): JsonResponse
    {
        ['room' => $room, 'token' => $token] = $createRoom->handle();

        return $this->roomResponse($room, TransferRole::Sender, $token, 201);
    }

    public function join(Request $request, string $code, JoinTransferRoom $joinRoom): JsonResponse
    {
        $limiterKey = 'transfer-join:'.hash('sha256', $request->ip().config('app.key'));

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_FAILED_JOINS)) {
            return $this->error(TransferRoomException::tooManyAttempts(RateLimiter::availableIn($limiterKey)));
        }

        $normalized = RoomCode::normalize($code);

        try {
            if ($normalized === null) {
                throw TransferRoomException::notFound();
            }

            ['room' => $room, 'token' => $token] = $joinRoom->handle($normalized);
        } catch (TransferRoomException $exception) {
            if ($exception->reason === 'not_found') {
                RateLimiter::hit($limiterKey, 600);
            }

            return $this->error($exception);
        }

        return $this->roomResponse($room, TransferRole::Receiver, $token);
    }

    public function signal(Request $request, string $code, PostTransferSignal $postSignal): JsonResponse
    {
        return $this->withRoom($request, $code, function (TransferRoom $room, TransferRole $role) use ($request, $postSignal): JsonResponse {
            // Đọc nguyên văn: middleware TrimStrings sẽ cắt mất "\r\n" cuối của SDP làm hỏng bắt tay.
            $payload = json_decode($request->getContent(), true)['payload'] ?? null;

            if (! is_array($payload) || $payload === [] || strlen((string) json_encode($payload)) > self::MAX_SIGNAL_BYTES) {
                return response()->json(['reason' => 'invalid', 'message' => 'Tin nhắn kết nối không hợp lệ.'], 422);
            }

            $signal = $postSignal->handle($room, $role, $payload);

            return response()->json(['id' => $signal->id], 201);
        });
    }

    public function poll(Request $request, string $code, PullTransferSignals $pullSignals, SyncNearbyPresence $syncNearby): JsonResponse
    {
        return $this->withRoom($request, $code, function (TransferRoom $room, TransferRole $role) use ($request, $pullSignals, $syncNearby): JsonResponse {
            $signals = $pullSignals->handle($room, $role, max(0, $request->integer('after')));
            $response = [
                'signals' => $signals->map(fn (TransferSignal $signal): array => ['id' => $signal->id, 'payload' => $signal->payload])->values(),
                'peerJoined' => $room->hasReceiver(),
                'expiresAt' => $room->expiresAt()->toIso8601String(),
            ];

            // Máy đang chờ (chưa ai vào) và đang dùng wifi: báo có mặt, lấy danh sách máy cùng mạng.
            if ($role === TransferRole::Sender && $request->boolean('nearby')) {
                $response['nearby'] = $syncNearby->handle($room, $this->networkHash($request), (string) $request->query('name', ''), (string) $request->query('device', ''));
            }

            return response()->json($response);
        });
    }

    /**
     * Mời một máy cùng mạng kết nối (máy kia phải đồng ý).
     */
    public function invite(Request $request, string $code, InviteNearbyDevice $inviteNearby): JsonResponse
    {
        return $this->withRoom($request, $code, function (TransferRoom $room, TransferRole $role) use ($request, $inviteNearby): JsonResponse {
            if ($role !== TransferRole::Sender) {
                throw TransferRoomException::forbidden();
            }
            $inviteNearby->handle($room, $this->networkHash($request), (string) $request->input('to', ''));

            return response()->json(null, 204);
        });
    }

    /**
     * Từ chối lời mời kết nối từ máy cùng mạng.
     */
    public function decline(Request $request, string $code, DeclineNearbyInvite $declineInvite): JsonResponse
    {
        return $this->withRoom($request, $code, function (TransferRoom $room) use ($declineInvite): JsonResponse {
            $declineInvite->handle($room);

            return response()->json(null, 204);
        });
    }

    /**
     * Kết nối thẳng thất bại: trả cấu hình ICE có máy chủ TURN (giới hạn tần suất và số lượt) để đi vòng.
     */
    public function turn(Request $request, string $code, IssueTurnCredentials $issueTurn): JsonResponse
    {
        return $this->withRoom($request, $code, fn (TransferRoom $room): JsonResponse => response()->json([
            'iceServers' => array_merge($this->iceServers(), $issueTurn->handle($room, hash('sha256', $request->ip().config('app.key')))),
            'maxRelayBytes' => (int) config('services.transfer.turn_max_bytes'),
        ]));
    }

    public function destroy(Request $request, string $code, CloseTransferRoom $closeRoom): JsonResponse
    {
        return $this->withRoom($request, $code, function (TransferRoom $room) use ($closeRoom): JsonResponse {
            $closeRoom->handle($room);

            return response()->json(null, 204);
        }, allowExpired: true);
    }

    /**
     * Tìm phòng theo mã và xác định vai trò theo token; hết hạn hoặc sai token thì trả lỗi.
     *
     * @param  Closure(TransferRoom, TransferRole): JsonResponse  $next
     */
    private function withRoom(Request $request, string $code, Closure $next, bool $allowExpired = false): JsonResponse
    {
        $normalized = RoomCode::normalize($code);
        $room = $normalized === null ? null : TransferRoom::query()->where('code', $normalized)->first();
        $role = $room?->roleFor((string) $request->header('X-Room-Token'));

        try {
            return match (true) {
                $room === null => throw TransferRoomException::notFound(),
                $role === null => throw TransferRoomException::forbidden(),
                ! $allowExpired && $room->isExpired() => throw TransferRoomException::expired(),
                default => $next($room, $role),
            };
        } catch (TransferRoomException $exception) {
            return $this->error($exception);
        }
    }

    private function roomResponse(TransferRoom $room, TransferRole $role, string $token, int $status = 200): JsonResponse
    {
        $joinUrl = route('transfer.show', ['ma' => $room->code]);
        // Chỉ đọc số liệu đã lưu (lịch cập nhật mỗi phút) để tạo phòng không phải chờ Metered.
        $turnExhausted = IssueTurnCredentials::enabled() && app(TurnQuota::class)->exhausted();
        $turnAvailable = IssueTurnCredentials::enabled() && ! $turnExhausted;

        return response()->json([
            'code' => $room->code,
            'role' => $role->value,
            'token' => $token,
            'peerJoined' => $room->hasReceiver(),
            'expiresAt' => $room->expiresAt()->toIso8601String(),
            'joinUrl' => $joinUrl,
            'qrSvg' => $role === TransferRole::Sender ? $this->qrSvg($joinUrl) : null,
            'iceServers' => $this->iceServers(),
            'turnAvailable' => $turnAvailable,
            'turnNotice' => $turnExhausted ? TransferRoomException::MONTHLY_LIMIT_MESSAGE : null,
            'maxRelayBytes' => (int) config('services.transfer.turn_max_bytes'),
        ], $status);
    }

    /**
     * Mã QR (SVG) chứa link vào phòng: quét bằng camera điện thoại là mở thẳng trang.
     */
    private function qrSvg(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd)))->writeString($url);

        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /**
     * @return list<array{urls: list<string>}>
     */
    private function iceServers(): array
    {
        $stun = config('services.transfer.stun_urls', []);

        return $stun === [] ? [] : [['urls' => array_values($stun)]];
    }

    private function networkHash(Request $request): ?string
    {
        return NetworkKey::of((string) $request->ip(), (string) config('app.key'));
    }

    private function error(TransferRoomException $exception): JsonResponse
    {
        return response()->json(['reason' => $exception->reason, 'message' => $exception->getMessage()], $exception->status);
    }
}
