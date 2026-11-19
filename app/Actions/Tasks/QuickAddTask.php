<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Domain\TaskQuickParse;
use App\Exceptions\HuiActionException;
use App\Models\Task;

/**
 * Thêm việc từ một dòng gõ nhanh (document/10-viec-can-lam.md V4, V7, VL-01).
 */
final class QuickAddTask
{
    public function __construct(private SaveTask $saveTask) {}

    /**
     * @param  list<string>  $ignore  Các loại người dùng đã bấm bỏ chip (TaskQuickParse::KIND_*)
     */
    public function handle(string $input, array $ignore = []): Task
    {
        $parsed = TaskQuickParse::parse($input, now()->toImmutable(), $ignore);

        if ($parsed->title === '') {
            throw new HuiActionException('Gõ tên việc trước, ví dụ "Đóng tiền điện mai".');
        }

        return $this->saveTask->handle(null, [
            'title' => mb_substr($parsed->title, 0, 200),
            'due_on' => $parsed->dueOn?->format('Y-m-d'),
            'due_time' => $parsed->dueTime,
            'important' => $parsed->important,
            'recurrence' => $parsed->repeat,
            'repeat_weekdays' => $parsed->weekdays,
            'repeat_day' => $parsed->repeatDay,
        ]);
    }
}
