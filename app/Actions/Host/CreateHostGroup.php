<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Domain\HuiSchedule;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;
use App\Models\Member;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tạo dây chủ hụi cùng N tay đã gán hụi viên và N kỳ (CH-01, CH-03) trong một transaction.
 */
final class CreateHostGroup
{
    /**
     * @param  array{name: string, face_value: int, total_slots: int, start_date: string, due_offset_days?: int, commission_amount?: int, note?: string|null}  $attributes
     * @param  list<string>  $memberIds  Hụi viên giữ tay 1..N, theo thứ tự.
     */
    public function handle(array $attributes, array $memberIds): HuiGroup
    {
        if (count($memberIds) !== $attributes['total_slots']) {
            throw new HuiActionException("Cần gán đủ {$attributes['total_slots']} tay cho hụi viên.");
        }

        $ownMemberCount = Member::query()->whereKey(array_unique($memberIds))->count();

        if ($ownMemberCount !== count(array_unique($memberIds))) {
            throw new HuiActionException('Hụi viên không tồn tại.');
        }

        return DB::transaction(function () use ($attributes, $memberIds): HuiGroup {
            // Dây do chính người dùng làm chủ: lưu tên họ vào cột chủ hụi bắt buộc.
            $group = HuiGroup::create($attributes + ['host_name' => Auth::user()->name]);

            foreach ($memberIds as $index => $memberId) {
                $group->hostSlots()->create(['slot_no' => $index + 1, 'member_id' => $memberId]);
            }

            $openDates = HuiSchedule::openDates(new DateTimeImmutable($group->start_date->toDateString()), $group->total_slots);

            foreach ($openDates as $roundNo => $openDate) {
                $group->rounds()->create(['round_no' => $roundNo, 'open_date' => $openDate->format('Y-m-d')]);
            }

            return $group->load(['hostSlots', 'rounds']);
        });
    }
}
