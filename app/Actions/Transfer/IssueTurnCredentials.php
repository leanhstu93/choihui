<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Exceptions\TransferRoomException;
use App\Models\TransferRoom;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Trả cấu hình máy chủ TURN của Metered (credential cố định trong .env) cho một phòng không nối
 * thẳng được. Không có gì ghi cứng ở frontend: trình duyệt chỉ biết TURN qua endpoint này, vốn có
 * giới hạn tần suất. Mỗi IP có số lượt mỗi ngày (hai máy trong một phòng chỉ tính một lượt); hết
 * hạn mức tháng thì không trả nữa.
 */
final class IssueTurnCredentials
{
    public function __construct(private readonly TurnQuota $quota) {}

    public static function enabled(): bool
    {
        return filled(config('services.transfer.turn_username'))
            && filled(config('services.transfer.turn_password'))
            && self::urls() !== [];
    }

    /**
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     *
     * @throws TransferRoomException
     */
    public function handle(TransferRoom $room, string $ipKey): array
    {
        if (! self::enabled()) {
            throw TransferRoomException::turnDisabled();
        }

        if ($this->quota->exhausted(refresh: true)) {
            throw TransferRoomException::turnMonthlyLimit();
        }

        // Máy thứ hai của cùng phòng không tính thêm lượt.
        if (Cache::add("transfer-turn-room:{$room->id}", true, now()->addDay())) {
            $dailyKey = "transfer-turn-ip:{$ipKey}";

            if (RateLimiter::tooManyAttempts($dailyKey, (int) config('services.transfer.turn_daily_per_ip'))) {
                Cache::forget("transfer-turn-room:{$room->id}");

                throw TransferRoomException::turnDailyLimit();
            }

            RateLimiter::hit($dailyKey, 86400);
        }

        return $this->servers();
    }

    /**
     * STUN không cần mật khẩu; TURN/TURNS dùng chung một bộ username/password.
     *
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    private function servers(): array
    {
        $urls = self::urls();
        $stun = array_values(array_filter($urls, fn (string $url): bool => str_starts_with($url, 'stun:')));
        $turn = array_values(array_filter($urls, fn (string $url): bool => ! str_starts_with($url, 'stun:')));

        return array_values(array_filter([
            $stun === [] ? null : ['urls' => $stun],
            $turn === [] ? null : [
                'urls' => $turn,
                'username' => (string) config('services.transfer.turn_username'),
                'credential' => (string) config('services.transfer.turn_password'),
            ],
        ]));
    }

    /**
     * @return list<string>
     */
    private static function urls(): array
    {
        return array_values(array_filter(
            array_map('trim', (array) config('services.transfer.turn_urls', [])),
            fn (string $url): bool => preg_match('/^(stun|turns?):/', $url) === 1,
        ));
    }
}
