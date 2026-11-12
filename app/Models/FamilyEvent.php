<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\FamilyCalendar;
use App\Domain\LunarCalendar;
use App\Domain\LunarDate;
use App\Enums\CalendarType;
use App\Enums\FamilyEventKind;
use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\FamilyEventFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Một ngày nhớ lặp lại hằng năm: giỗ, sinh nhật, kỷ niệm (document/09-gia-dinh.md).
 *
 * @property string $id
 * @property int $user_id
 * @property string $title
 * @property FamilyEventKind $kind
 * @property CalendarType $calendar
 * @property int $day
 * @property int $month
 * @property int|null $origin_year
 * @property int $remind_days_before
 * @property string|null $note
 */
#[Fillable(['title', 'kind', 'calendar', 'day', 'month', 'origin_year', 'remind_days_before', 'note'])]
class FamilyEvent extends Model
{
    /** @use HasFactory<FamilyEventFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'remind_days_before' => 3,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FamilyEventKind::class,
            'calendar' => CalendarType::class,
            'day' => 'integer',
            'month' => 'integer',
            'origin_year' => 'integer',
            'remind_days_before' => 'integer',
        ];
    }

    /**
     * Lần tới tính từ $from, kể cả chính ngày đó (document/09-gia-dinh.md mục 4).
     */
    public function nextOccurrence(DateTimeImmutable $from): CarbonImmutable
    {
        return CarbonImmutable::instance(FamilyCalendar::nextOccurrence($this->calendar->isLunar(), $this->day, $this->month, $from));
    }

    /**
     * "Tròn 60 tuổi", "Giỗ đầu"... cho lần $occurrence; null khi không có năm gốc.
     */
    public function yearsLabel(DateTimeImmutable $occurrence): ?string
    {
        $years = FamilyCalendar::yearsSince($this->calendar->isLunar(), $this->origin_year, $occurrence);

        return $years === null ? null : $this->kind->yearsLabel($years);
    }

    /**
     * Ngày đã chọn, ví dụ "10/12 ÂL" hoặc "29/02".
     */
    public function dateLabel(): string
    {
        return $this->calendar->isLunar()
            ? (new LunarDate($this->day, $this->month, 0))->label()
            : sprintf('%02d/%02d', $this->day, $this->month);
    }

    /**
     * Ngày âm của một ngày dương, để hiện kèm.
     */
    public static function lunarLabel(DateTimeImmutable $date): string
    {
        return LunarCalendar::fromSolar($date)->label();
    }
}
