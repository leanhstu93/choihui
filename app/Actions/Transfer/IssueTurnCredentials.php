<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Exceptions\TransferRoomException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Xin mật khẩu TURN tạm thời (Cloudflare) cho một máy không nối thẳng được. Chỉ gọi khi kết nối
 * trực tiếp đã thất bại, nên mỗi lần cấp là một lượt thật sự đi vòng: đếm theo IP mỗi ngày và tổng
 * cả tháng để không vượt mức miễn phí. Mật khẩu hết hạn sau turn_ttl giây.
 */
final class IssueTurnCredentials
{
    private const ENDPOINT = 'https://rtc.live.cloudflare.com/v1/turn/keys/%s/credentials/generate-ice-servers';

    public static function enabled(): bool
    {
        return filled(config('services.transfer.turn_key_id')) && filled(config('services.transfer.turn_api_token'));
    }

    /**
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     *
     * @throws TransferRoomException
     */
    public function handle(string $ipKey): array
    {
        if (! self::enabled()) {
            throw new TransferRoomException('turn_disabled', 'Mạng đang chặn kết nối thẳng giữa hai máy. Hãy cho hai máy dùng chung một wifi rồi thử lại.', 503);
        }

        $dailyKey = "transfer-turn-ip:{$ipKey}";
        $monthlyKey = 'transfer-turn-month:'.now()->format('Y-m');

        if (RateLimiter::tooManyAttempts($dailyKey, (int) config('services.transfer.turn_daily_per_ip'))) {
            throw new TransferRoomException('turn_daily_limit', 'Hôm nay bạn đã dùng hết lượt đi qua máy chủ trung chuyển. Hãy cho hai máy dùng chung một wifi để gửi trực tiếp (không giới hạn), hoặc thử lại vào ngày mai.', 429);
        }
        if ((int) Cache::get($monthlyKey, 0) >= (int) config('services.transfer.turn_monthly_sessions')) {
            throw new TransferRoomException('turn_monthly_limit', 'Máy chủ trung chuyển đã hết lượt trong tháng này. Hãy cho hai máy dùng chung một wifi để gửi trực tiếp.', 429);
        }

        try {
            $response = Http::timeout(8)
                ->withToken((string) config('services.transfer.turn_api_token'))
                ->post(sprintf(self::ENDPOINT, config('services.transfer.turn_key_id')), ['ttl' => (int) config('services.transfer.turn_ttl')])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            report($exception);

            throw new TransferRoomException('turn_unavailable', 'Máy chủ trung chuyển đang bận. Hãy thử lại sau ít phút, hoặc cho hai máy dùng chung một wifi.', 503);
        }

        RateLimiter::hit($dailyKey, 86400);
        Cache::add($monthlyKey, 0, now()->addDays(40));
        Cache::increment($monthlyKey);

        return $this->browserSafe($response['iceServers'] ?? []);
    }

    /**
     * Bỏ địa chỉ cổng 53 (trình duyệt chặn, làm chậm bắt tay) theo khuyến nghị của Cloudflare.
     *
     * @param  mixed  $servers
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    private function browserSafe($servers): array
    {
        $servers = is_array($servers) && array_is_list($servers) ? $servers : [$servers];
        $result = [];

        foreach ($servers as $server) {
            if (! is_array($server) || ! isset($server['urls'])) {
                continue;
            }

            $urls = array_values(array_filter((array) $server['urls'], fn ($url): bool => is_string($url) && ! preg_match('/:53(\?|$)/', $url)));

            if ($urls !== []) {
                $result[] = array_filter(['urls' => $urls, 'username' => $server['username'] ?? null, 'credential' => $server['credential'] ?? null], fn ($value): bool => $value !== null);
            }
        }

        return $result;
    }
}
