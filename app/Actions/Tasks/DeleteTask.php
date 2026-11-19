<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Xóa một việc (document/10-viec-can-lam.md V15). Việc lặp chưa xong mà chọn "Chỉ xóa lần này"
 * thì tạo lần tới trước rồi mới xóa, để lịch lặp không bị đứt.
 */
final class DeleteTask
{
    public function __construct(private CompleteTask $completeTask) {}

    public function handle(Task $task, bool $keepRepeating = false): void
    {
        DB::transaction(function () use ($task, $keepRepeating): void {
            if ($keepRepeating && $task->isRecurring() && ! $task->isDone()) {
                $this->completeTask->handle($task);
            }

            $task->delete();
        });
    }
}
