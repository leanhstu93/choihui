<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\HuiGroup;

/**
 * Gợi ý mệnh giá và tiền thảo cho form tạo / sửa dây: các số đã dùng ở dây gần
 * đây trước, rồi đến số thường gặp; bỏ số 0 và số trùng, tối đa 2.
 */
trait SuggestsGroupAmounts
{
    /** @var list<int> */
    private const array DEFAULT_FACE_VALUES = [1_000_000, 2_000_000, 3_000_000, 5_000_000];

    /** @var list<int> */
    private const array DEFAULT_COMMISSIONS = [200_000, 500_000, 1_000_000];

    /**
     * @return array{faceValueSuggestions: list<int>, commissionSuggestions: list<int>}
     */
    private function groupAmountSuggestions(): array
    {
        $recentGroups = HuiGroup::query()->latest()->limit(20)->get(['face_value', 'commission_amount']);

        return [
            'faceValueSuggestions' => $this->suggestAmounts($recentGroups->pluck('face_value')->all(), self::DEFAULT_FACE_VALUES),
            'commissionSuggestions' => $this->suggestAmounts($recentGroups->pluck('commission_amount')->all(), self::DEFAULT_COMMISSIONS),
        ];
    }

    /**
     * @param  list<int>  $usedAmounts
     * @param  list<int>  $defaultAmounts
     * @return list<int>
     */
    private function suggestAmounts(array $usedAmounts, array $defaultAmounts): array
    {
        $amounts = array_unique(array_filter([...$usedAmounts, ...$defaultAmounts], fn (int $amount): bool => $amount > 0));

        return array_slice(array_values($amounts), 0, 2);
    }
}
