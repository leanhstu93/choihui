<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Domain\LunarCalendar;
use App\Domain\TaskRecurrence;
use App\Enums\TaskRepeat;
use App\Models\Task;
use DateTimeImmutable;

/**
 * Thêm hoặc sửa một việc (document/10-viec-can-lam.md V2, V6). Đã qua StoreTaskRequest.
 *
 * Chuẩn hóa phần lặp: thứ và ngày neo chỉ giữ cho kiểu lặp cần chúng, trống thì lấy theo hạn.
 */
final class SaveTask
{
    /**
     * @param  array{title: string, note?: string|null, due_on?: string|null, due_time?: string|null, important?: bool, recurrence?: string|TaskRepeat, repeat_weekdays?: int, repeat_day?: int|null}  $attributes
     */
    public function handle(?Task $task, array $attributes): Task
    {
        $attributes = self::normalize($attributes);

        if ($task === null) {
            return Task::query()->create($attributes);
        }

        $task->fill($attributes)->save();

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(array $attributes): array
    {
        $recurrence = $attributes['recurrence'] ?? TaskRepeat::None;
        $recurrence = $recurrence instanceof TaskRepeat ? $recurrence : TaskRepeat::from((string) $recurrence);
        $dueOn = isset($attributes['due_on']) && $attributes['due_on'] !== '' ? new DateTimeImmutable((string) $attributes['due_on']) : null;

        $attributes['title'] = trim((string) $attributes['title']);
        $attributes['note'] = isset($attributes['note']) && trim((string) $attributes['note']) !== '' ? trim((string) $attributes['note']) : null;
        $attributes['due_on'] = $dueOn?->format('Y-m-d');
        $attributes['due_time'] = $dueOn !== null && ($attributes['due_time'] ?? '') !== '' ? $attributes['due_time'] : null;
        $attributes['recurrence'] = $dueOn === null ? TaskRepeat::None : $recurrence;
        $attributes['repeat_weekdays'] = $attributes['recurrence'] === TaskRepeat::Weekly && $dueOn !== null
            ? ((int) ($attributes['repeat_weekdays'] ?? 0) ?: TaskRecurrence::weekdayBit($dueOn))
            : 0;
        $attributes['repeat_day'] = match (true) {
            $dueOn === null => null,
            $attributes['recurrence'] === TaskRepeat::Monthly, $attributes['recurrence'] === TaskRepeat::Yearly => $attributes['repeat_day'] ?? (int) $dueOn->format('j'),
            $attributes['recurrence'] === TaskRepeat::MonthlyLunar => $attributes['repeat_day'] ?? LunarCalendar::fromSolar($dueOn)->day,
            default => null,
        };

        return $attributes;
    }
}
