<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Hạn mức TURN miễn phí của tháng. Hỏi Metered dung lượng đã dùng (lưu tạm vài phút); còn chưa đủ
 * cho một lượt gửi tối đa (turn_max_bytes) thì coi như hết, trang chỉ dùng kết nối trực tiếp.
 */
final class TurnQuota
{
    /** Số liệu dùng lại trong ngần này giây trước khi hỏi Metered lại. */
    public const CACHE_SECONDS = 600;

    public function __construct(private readonly MeteredTurnClient $client) {}

    /**
     * $refresh = false: chỉ đọc số liệu đã lưu, không gọi mạng (dùng khi tạo phòng cho nhanh).
     * Chưa có số liệu hoặc Metered lỗi thì coi như còn: Metered tự chặn khi hết thật.
     */
    public function exhausted(bool $refresh = false): bool
    {
        $status = Cache::get(self::cacheKey());

        if (! is_array($status) && $refresh) {
            $status = $this->refresh();
        }

        return is_array($status) && ($status['exhausted'] ?? false) === true;
    }

    /**
     * Hỏi Metered rồi lưu kết quả. Gọi từ lịch chạy mỗi phút (transfer:turn-pool).
     *
     * @return array{usage: float, quota: float, exhausted: bool}|null
     */
    public function refresh(): ?array
    {
        if (! MeteredTurnClient::configured()) {
            return null;
        }

        try {
            ['usage' => $usage, 'quota' => $quota] = $this->client->currentUsage();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $quota ??= (float) config('services.transfer.turn_monthly_gb');
        $reserve = (int) config('services.transfer.turn_max_bytes') / 1024 ** 3;
        $status = ['usage' => $usage, 'quota' => $quota, 'exhausted' => $usage + $reserve >= $quota];

        Cache::put(self::cacheKey(), $status, self::CACHE_SECONDS);

        return $status;
    }

    public static function cacheKey(): string
    {
        return 'transfer-turn-usage:'.now()->format('Y-m');
    }
}
