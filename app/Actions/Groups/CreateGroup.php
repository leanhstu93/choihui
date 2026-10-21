<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Domain\HuiSchedule;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tạo dây hụi cùng các tay của mình và N kỳ (FR-01, FR-02, FR-03) trong một transaction.
 */
final class CreateGroup
{
    /**
     * @param  array{name: string, face_value: int, total_slots: int, start_date: string, host_name: string, host_phone?: string|null, due_offset_days?: int, commission_amount?: int, note?: string|null, type?: string}  $attributes
     * @param  list<string>  $slotLabels
     */
    public function handle(array $attributes, array $slotLabels): HuiGroup
    {
        if ($slotLabels === []) {
            throw new HuiActionException('Cần ít nhất một tay của mình.');
        }

        if (count($slotLabels) > $attributes['total_slots']) {
            throw new HuiActionException('Số tay của mình không được vượt quá tổng số tay của dây.');
        }

        return DB::transaction(function () use ($attributes, $slotLabels): HuiGroup {
            $group = HuiGroup::create($attributes);

            foreach ($slotLabels as $label) {
                $group->slots()->create(['label' => $label]);
            }

            $openDates = HuiSchedule::openDates(new DateTimeImmutable($group->start_date->toDateString()), $group->total_slots);

            foreach ($openDates as $roundNo => $openDate) {
                $group->rounds()->create(['round_no' => $roundNo, 'open_date' => $openDate->format('Y-m-d')]);
            }

            return $group->load(['slots', 'rounds']);
        });
    }
}
