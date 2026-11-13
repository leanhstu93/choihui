<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Gọi REST API của Metered (Open Relay, gói miễn phí không cần thẻ). Khóa bí mật chỉ nằm ở server;
 * trình duyệt chỉ nhận mật khẩu tạm thời của từng phiên.
 *
 * Tài liệu: https://www.metered.ca/docs/turn-rest-api/post-create-credential
 */
final class MeteredTurnClient
{
    public static function configured(): bool
    {
        return filled(config('services.transfer.metered_app')) && filled(config('services.transfer.metered_secret_key'));
    }

    /**
     * Tạo mật khẩu TURN hết hạn sau $seconds giây, rồi lấy danh sách máy chủ ICE đi kèm.
     *
     * @return array{username: string, iceServers: list<array{urls: list<string>, username?: string, credential?: string}>}
     */
    public function createCredential(int $seconds, string $label): array
    {
        $created = $this->http()
            ->post('/api/v1/turn/credential?'.http_build_query(['secretKey' => config('services.transfer.metered_secret_key')]), [
                'expiryInSeconds' => $seconds,
                'label' => $label,
            ])
            ->throw()
            ->json();

        $username = (string) ($created['username'] ?? '');
        $apiKey = (string) ($created['apiKey'] ?? '');

        if ($username === '' || $apiKey === '') {
            throw new \RuntimeException('Metered trả về mật khẩu TURN thiếu username/apiKey.');
        }

        $servers = $this->http()
            ->get('/api/v1/turn/credentials', ['apiKey' => $apiKey])
            ->throw()
            ->json();

        $iceServers = self::normalize($servers);

        if ($iceServers === []) {
            throw new \RuntimeException('Metered trả về danh sách máy chủ TURN rỗng.');
        }

        return ['username' => $username, 'iceServers' => $iceServers];
    }

    /**
     * Dung lượng TURN đã dùng và hạn mức của chu kỳ hiện tại, tính bằng GB.
     *
     * @return array{usage: float, quota: float|null}
     */
    public function currentUsage(): array
    {
        $data = $this->http()
            ->get('/api/v1/turn/current_usage', ['secretKey' => config('services.transfer.metered_secret_key')])
            ->throw()
            ->json();

        $usage = $data['usageInGB'] ?? null;
        $quota = $data['quotaInGB'] ?? null;

        if (! is_numeric($usage)) {
            throw new \RuntimeException('Metered trả về số liệu dung lượng không đọc được.');
        }

        return ['usage' => (float) $usage, 'quota' => is_numeric($quota) && (float) $quota > 0 ? (float) $quota : null];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://'.config('services.transfer.metered_app').'.metered.live')
            ->acceptJson()
            ->timeout(8);
    }

    /**
     * Chuẩn hóa về dạng RTCIceServer: urls luôn là mảng, bỏ cổng 53 (trình duyệt chặn, làm chậm
     * bắt tay) và mục không hợp lệ.
     *
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    public static function normalize(mixed $servers): array
    {
        if (! is_array($servers)) {
            return [];
        }
        $servers = array_is_list($servers) ? $servers : [$servers];
        $result = [];

        foreach ($servers as $server) {
            if (! is_array($server) || ! isset($server['urls'])) {
                continue;
            }

            $urls = array_values(array_filter((array) $server['urls'], fn ($url): bool => is_string($url) && preg_match('/^(stun|turns?):/', $url) === 1 && preg_match('/:53(\?|$)/', $url) !== 1));

            if ($urls !== []) {
                $result[] = array_filter([
                    'urls' => $urls,
                    'username' => isset($server['username']) ? (string) $server['username'] : null,
                    'credential' => isset($server['credential']) ? (string) $server['credential'] : null,
                ], fn ($value): bool => $value !== null);
            }
        }

        return $result;
    }
}
