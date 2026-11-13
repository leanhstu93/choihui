<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Exceptions\TransferRoomException;
use App\Models\TransferRoom;
use App\Models\TransferTurnCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Giao mật khẩu TURN tạm thời (Open Relay / Metered) cho một phòng không nối thẳng được. Mỗi phòng
 * nhận riêng một mật khẩu lấy từ kho tạo sẵn; hai máy trong phòng hỏi thì nhận cùng một mật khẩu
 * và chỉ tính một lượt. Giới hạn số lượt theo IP mỗi ngày; hết hạn mức tháng thì không cấp nữa.
 */
final class IssueTurnCredentials
{
    public function __construct(
        private readonly TurnQuota $quota,
        private readonly RefillTurnPool $pool,
    ) {}

    public static function enabled(): bool
    {
        return MeteredTurnClient::configured();
    }

    /**
     * @return array{iceServers: list<array<string, mixed>>, readyIn: int}
     *
     * @throws TransferRoomException
     */
    public function handle(TransferRoom $room, string $ipKey): array
    {
        if (! self::enabled()) {
            throw TransferRoomException::turnDisabled();
        }

        // Khóa phòng: hai máy hỏi cùng lúc vẫn chỉ nhận một mật khẩu, tính một lượt.
        return DB::transaction(function () use ($room, $ipKey): array {
            TransferRoom::query()->whereKey($room->id)->lockForUpdate()->first();

            $existing = TransferTurnCredential::query()->where('transfer_room_id', $room->id)->where('expires_at', '>', now())->first();
            if ($existing !== null) {
                return $this->payload($existing);
            }

            if ($this->quota->exhausted(refresh: true)) {
                throw TransferRoomException::turnMonthlyLimit();
            }

            $dailyKey = "transfer-turn-ip:{$ipKey}";
            if (RateLimiter::tooManyAttempts($dailyKey, (int) config('services.transfer.turn_daily_per_ip'))) {
                throw TransferRoomException::turnDailyLimit();
            }

            $credential = $this->claimFromPool($room) ?? $this->createForRoom($room);
            RateLimiter::hit($dailyKey, 86400);

            return $this->payload($credential);
        });
    }

    /**
     * Lấy mật khẩu đã sẵn sàng sớm nhất; chưa có thì lấy cái sắp sẵn sàng.
     */
    private function claimFromPool(TransferRoom $room): ?TransferTurnCredential
    {
        $credential = TransferTurnCredential::query()->available()->orderBy('ready_at')->lockForUpdate()->first();
        $credential?->update(['transfer_room_id' => $room->id, 'issued_at' => now()]);

        return $credential;
    }

    /**
     * Kho trống (vd. lịch chưa kịp chạy): tạo ngay một mật khẩu, máy phải chờ nó lan truyền.
     *
     * @throws TransferRoomException
     */
    private function createForRoom(TransferRoom $room): TransferTurnCredential
    {
        try {
            $credential = $this->pool->create();
        } catch (Throwable $exception) {
            report($exception);

            throw TransferRoomException::turnUnavailable();
        }

        $credential->update(['transfer_room_id' => $room->id, 'issued_at' => now()]);

        return $credential;
    }

    /**
     * @return array{iceServers: list<array<string, mixed>>, readyIn: int}
     */
    private function payload(TransferTurnCredential $credential): array
    {
        return ['iceServers' => $credential->ice_servers, 'readyIn' => $credential->secondsUntilReady()];
    }
}
