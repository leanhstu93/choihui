<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Exceptions\HuiActionException;
use App\Models\Task;
use Carbon\CarbonImmutable;

/**
 * Dời hạn một việc (document/10-viec-can-lam.md V9). Giờ giữ nguyên, trừ khi truyền giờ mới.
 * Việc lặp chỉ dời lần này; ngày neo của kiểu lặp không đổi.
 */
final class RescheduleTask
{
    public const string KEEP_TIME = 'keep';

    public function handle(Task $task, ?CarbonImmutable $dueOn, ?string $dueTime = self::KEEP_TIME): void
    {
        if ($dueOn === null && $task->isRecurring()) {
            throw new HuiActionException('Việc lặp lại luôn cần có ngày. Muốn bỏ ngày thì tắt lặp trước.');
        }

        $task->due_on = $dueOn;
        $task->due_time = $dueOn === null ? null : ($dueTime === self::KEEP_TIME ? $task->due_time : $dueTime);
        $task->save();
    }
}
