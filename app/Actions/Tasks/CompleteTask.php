<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Support\Facades\DB;

/**
 * Đánh dấu xong (document/10-viec-can-lam.md V6, V8). Việc lặp thì tạo việc mới cho lần tới,
 * chép các bước nhỏ sang và bỏ tích.
 */
final class CompleteTask
{
    /**
     * @return Task|null Việc lần tới vừa tạo, nếu có
     */
    public function handle(Task $task): ?Task
    {
        if ($task->isDone()) {
            return null;
        }

        return DB::transaction(function () use ($task): ?Task {
            $now = now()->toImmutable();
            $nextDueOn = $task->nextDueOn($now);
            $next = null;

            if ($nextDueOn !== null) {
                $next = $task->replicate(['completed_at', 'spawned_task_id', 'created_at', 'updated_at']);
                $next->due_on = $nextDueOn;
                $next->save();

                $task->steps->each(function (TaskStep $step) use ($next): void {
                    $copy = $step->replicate(['created_at', 'updated_at']);
                    $copy->task_id = $next->id;
                    $copy->done = false;
                    $copy->save();
                });
            }

            $task->completed_at = $now;
            $task->spawned_task_id = $next?->id;
            $task->save();

            return $next;
        });
    }
}
