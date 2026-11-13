<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HolidayBreak;
use App\Domain\WorkHolidays;
use App\Models\OfficialHolidayBreak;

/**
 * Lịch nghỉ lễ của năm: lịch chính thức đã nhập, còn lại là lịch dự kiến tự tính (Domain\WorkHolidays).
 */
final class HolidaySchedule
{
    /**
     * @return list<HolidayBreak>
     */
    public function forYear(int $year): array
    {
        $official = OfficialHolidayBreak::query()->where('year', $year)->get()
            ->mapWithKeys(fn (OfficialHolidayBreak $entry): array => [$entry->kind->value => [
                'start' => $entry->starts_on->toDateTimeImmutable(),
                'end' => $entry->ends_on->toDateTimeImmutable(),
                'makeUpOn' => $entry->make_up_on?->toDateTimeImmutable(),
                'source' => $entry->source,
            ]])
            ->all();

        return WorkHolidays::forYear($year, $official);
    }

    /**
     * Các đợt nghỉ có ngày nằm trong năm dương $year (kể cả Tết Dương lịch năm sau bắt đầu từ cuối năm nay).
     *
     * @return list<HolidayBreak>
     */
    public function overlapping(int $year): array
    {
        $breaks = array_merge($this->forYear($year - 1), $this->forYear($year), $this->forYear($year + 1));

        return array_values(array_filter($breaks, fn (HolidayBreak $break): bool => (int) $break->start->format('Y') <= $year && (int) $break->end->format('Y') >= $year));
    }

    /**
     * Gọn cho trình duyệt.
     *
     * @return array{kind: string, name: string, year: int, start: string, end: string, length: int, official: bool, source: ?string, makeUpOn: ?string, days: array<string, string>}
     */
    public static function toArray(HolidayBreak $break): array
    {
        return [
            'kind' => $break->kind->value,
            'name' => $break->kind->label(),
            'year' => $break->year,
            'start' => $break->start->format('Y-m-d'),
            'end' => $break->end->format('Y-m-d'),
            'length' => $break->length(),
            'official' => $break->isOfficial,
            'source' => $break->source,
            'makeUpOn' => $break->makeUpOn?->format('Y-m-d'),
            'days' => $break->days,
        ];
    }
}
