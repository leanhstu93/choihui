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
}
