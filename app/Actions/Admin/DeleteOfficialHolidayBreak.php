<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminAction;
use App\Enums\HolidayBreakKind;
use App\Models\OfficialHolidayBreak;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Xóa lịch nghỉ chính thức của một đợt: lịch quay về bản dự kiến tự tính.
 */
final class DeleteOfficialHolidayBreak
{
    public function __construct(private RecordAdminLog $recordAdminLog) {}

    public function handle(User $admin, int $year, HolidayBreakKind $kind): void
    {
        DB::transaction(function () use ($admin, $year, $kind): void {
            $deleted = OfficialHolidayBreak::query()->where('year', $year)->where('kind', $kind)->delete();

            if ($deleted > 0) {
                $this->recordAdminLog->handle($admin, AdminAction::DeleteHolidayBreak, meta: ['year' => $year, 'kind' => $kind->value]);
            }
        });
    }
}
