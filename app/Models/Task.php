<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\TaskRecurrence;
use App\Enums\TaskRepeat;
use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một việc cần làm (document/10-viec-can-lam.md V2).
 *
 * @property string $id
 * @property int $user_id
 * @property string $title
 * @property string|null $note
 * @property CarbonImmutable|null $due_on
 * @property string|null $due_time "HH:MM"
 * @property bool $important
 * @property TaskRepeat $recurrence
 * @property int $repeat_weekdays
 * @property int|null $repeat_day
 * @property CarbonImmutable|null $completed_at
 * @property string|null $spawned_task_id
 * @property CarbonImmutable $created_at
 * @property-read Collection<int, TaskStep> $steps
 */
#[Fillable(['title', 'note', 'due_on', 'due_time', 'important', 'recurrence', 'repeat_weekdays', 'repeat_day'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const int MAX_STEPS = 20;

    public const int KEEP_DONE_DAYS = 90;

    public const array WEEKDAYS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'important' => false,
        'recurrence' => 'none',
        'repeat_weekdays' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'immutable_date',
            'important' => 'boolean',
            'recurrence' => TaskRepeat::class,
            'repeat_weekdays' => 'integer',
            'repeat_day' => 'integer',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Giờ lưu trong MySQL là "HH:MM:SS"; ngoài app chỉ dùng "HH:MM".
     *
     * @return Attribute<string|null, string|null>
     */
    protected function dueTime(): Attribute
    {
        return Attribute::get(
            fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5),
        );
    }

    /**
     * @return HasMany<TaskStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TaskStep::class)->orderBy('position');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isRecurring(): bool
    {
        return $this->recurrence !== TaskRepeat::None;
    }

    /**
     * Quá hạn khi hạn trước hôm nay (mục 4). Hạn hôm nay đã qua giờ vẫn thuộc nhóm Hôm nay.
     */
    public function isOverdue(DateTimeImmutable $today): bool
    {
        return ! $this->isDone() && $this->due_on !== null && $this->due_on->format('Y-m-d') < $today->format('Y-m-d');
    }

    /**
     * Hạn của lần tới khi đánh dấu xong vào ngày $today (mục 4).
     */
    public function nextDueOn(DateTimeImmutable $today): ?CarbonImmutable
    {
        if (! $this->isRecurring() || $this->due_on === null) {
            return null;
        }

        return CarbonImmutable::instance(TaskRecurrence::next(
            $this->recurrence,
            $this->due_on->toDateTimeImmutable(),
            $today,
            $this->repeat_weekdays,
            $this->repeat_day,
        ));
    }

    /**
     * Nhãn hạn có màu (VL-07): đỏ "Trễ 2 ngày", cam "Hôm nay 08:00", xanh "Mai", xám "Thứ 6, 27/11".
     *
     * @return array{label: string, tone: 'late'|'today'|'soon'|'later'}|null
     */
    public function dueBadge(CarbonImmutable $today): ?array
    {
        if ($this->due_on === null) {
            return null;
        }

        $days = (int) $today->startOfDay()->diffInDays($this->due_on, false);
        $time = $this->due_time === null ? '' : ' '.$this->due_time;

        return match (true) {
            $days < 0 => ['label' => 'Trễ '.-$days.' ngày', 'tone' => 'late'],
            $days === 0 => ['label' => 'Hôm nay'.$time, 'tone' => 'today'],
            $days === 1 => ['label' => 'Mai'.$time, 'tone' => 'soon'],
            $days < 7 => ['label' => self::WEEKDAYS[$this->due_on->dayOfWeek].', '.$this->due_on->format('d/m').$time, 'tone' => 'later'],
            $this->due_on->year === $today->year => ['label' => $this->due_on->format('d/m').$time, 'tone' => 'later'],
            default => ['label' => $this->due_on->format('d/m/Y').$time, 'tone' => 'later'],
        };
    }

    /**
     * "Thứ 2 và thứ 5", "Rằm", "Ngày 31"… mô tả ngắn kiểu lặp, cho dòng việc và form.
     */
    public function recurrenceLabel(): ?string
    {
        return match ($this->recurrence) {
            TaskRepeat::None => null,
            TaskRepeat::Daily => 'Hằng ngày',
            TaskRepeat::Weekly => $this->repeat_weekdays === TaskRecurrence::ALL_WEEKDAYS ? 'Hằng ngày' : 'Hằng tuần: '.self::weekdaysLabel($this->repeat_weekdays),
            TaskRepeat::Monthly => 'Ngày '.$this->repeat_day.' hằng tháng',
            TaskRepeat::MonthlyLunar => match ($this->repeat_day) {
                15 => 'Rằm hằng tháng (âm)',
                1 => 'Mùng 1 hằng tháng (âm)',
                default => ($this->repeat_day !== null && $this->repeat_day <= 10 ? 'Mùng ' : 'Ngày ').$this->repeat_day.' âm hằng tháng',
            },
            TaskRepeat::Yearly => 'Hằng năm, '.$this->due_on?->format('d/m'),
        };
    }

    /**
     * Bitmask thứ (bit 0 = thứ 2) → "T2, T5, CN".
     */
    public static function weekdaysLabel(int $mask): string
    {
        $labels = [];

        for ($iso = 1; $iso <= 7; $iso++) {
            if (($mask & (1 << ($iso - 1))) !== 0) {
                $labels[] = $iso === 7 ? 'CN' : 'T'.($iso + 1);
            }
        }

        return implode(', ', $labels);
    }
}
