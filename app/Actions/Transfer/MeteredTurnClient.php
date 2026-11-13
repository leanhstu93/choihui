<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use Illuminate\Support\Facades\Http;

/**
 * Đọc dung lượng TURN đã dùng trong tháng qua REST API của Metered. Không bắt buộc: chỉ chạy khi có
 * METERED_APP và METERED_SECRET_KEY. Khóa bí mật chỉ nằm ở server.
 *
 * Tài liệu: https://www.metered.ca/docs/turn-rest-api/get-current-usage
 */
final class MeteredTurnClient
{
    public static function configured(): bool
    {
        return filled(config('services.transfer.metered_app')) && filled(config('services.transfer.metered_secret_key'));
    }

    /**
     * Dung lượng TURN đã dùng và hạn mức của chu kỳ hiện tại, tính bằng GB.
     *
     * @return array{usage: float, quota: float|null}
     */
    public function currentUsage(): array
    {
        $data = Http::baseUrl('https://'.config('services.transfer.metered_app').'.metered.live')
            ->acceptJson()
            ->timeout(8)
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
}
