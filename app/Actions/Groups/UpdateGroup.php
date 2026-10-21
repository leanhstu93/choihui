<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Rounds\SyncCashEntries;
use App\Exceptions\ConfirmationRequiredException;
use App\Models\HuiGroup;
use Illuminate\Support\Facades\DB;

/**
 * Sửa thông tin dây. Mệnh giá, số tay và ngày bắt đầu quyết định lịch kỳ nên không
 * sửa ở đây; đổi tiền thảo thì tính lại khoản nhận.
 */
final class UpdateGroup
{
    public function __construct(private SyncCashEntries $syncCashEntries) {}

    /**
     * @param  array{name?: string, host_name?: string, host_phone?: string|null, due_offset_days?: int, commission_amount?: int, note?: string|null}  $attributes
     *
     * @throws ConfirmationRequiredException khi khoản nhận đã ghi tiền bị thay đổi và $confirmed = false.
     */
    public function handle(HuiGroup $group, array $attributes, bool $confirmed = false): HuiGroup
    {
        $editable = array_intersect_key($attributes, array_flip(['name', 'host_name', 'host_phone', 'due_offset_days', 'commission_amount', 'note']));

        return DB::transaction(function () use ($group, $editable, $confirmed): HuiGroup {
            $group->update($editable);

            if ($group->wasChanged('commission_amount')) {
                $conflicts = $this->syncCashEntries->handle($group, overwritePaid: $confirmed);

                if ($conflicts !== []) {
                    throw new ConfirmationRequiredException(count($conflicts));
                }
            }

            return $group;
        });
    }
}
