<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminAction;
use App\Enums\HolidayBreakKind;
use App\Models\OfficialHolidayBreak;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lưu lịch nghỉ chính thức của một đợt, thay cho lịch dự kiến. Ghi nhật ký quản trị.
 */
final class SaveOfficialHolidayBreak
{
    public function __construct(private RecordAdminLog $recordAdminLog) {}

    /**
     * @param  array{year: int|string, kind: string, starts_on: string, ends_on: string, make_up_on?: ?string, source?: ?string}  $data
     */
    public function handle(User $admin, array $data): OfficialHolidayBreak
    {
        return DB::transaction(function () use ($admin, $data): OfficialHolidayBreak {
            $entry = OfficialHolidayBreak::query()->updateOrCreate(
                ['year' => (int) $data['year'], 'kind' => HolidayBreakKind::from($data['kind'])],
                [
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'],
                    'make_up_on' => ($data['make_up_on'] ?? null) ?: null,
                    'source' => ($data['source'] ?? null) ?: null,
                ],
            );

            $this->recordAdminLog->handle($admin, AdminAction::SaveHolidayBreak, meta: [
                'year' => $entry->year,
                'kind' => $entry->kind->value,
                'starts_on' => $entry->starts_on->toDateString(),
                'ends_on' => $entry->ends_on->toDateString(),
            ]);

            return $entry;
        });
    }
}
