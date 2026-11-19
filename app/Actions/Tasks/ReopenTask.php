<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Hoàn tác "xong" hoặc bấm "Làm lại" (document/10-viec-can-lam.md V8, V13). Lần tới mà việc
 * lặp đã tạo sẽ bị xóa nếu người dùng chưa đụng tới nó (chưa sửa, chưa xong, chưa tích bước nào).
 */
final class ReopenTask
{
    public function handle(Task $task): void
    {
        if (! $task->isDone()) {
            return;
        }

        DB::transaction(function () use ($task): void {
            $spawned = $task->spawned_task_id === null ? null : Task::query()->find($task->spawned_task_id);

            $task->completed_at = null;
            $task->spawned_task_id = null;
            $task->save();

            if ($spawned !== null && self::untouched($spawned)) {
                $spawned->delete();
            }
        });
    }

    private static function untouched(Task $task): bool
    {
        return ! $task->isDone()
            && $task->updated_at?->equalTo($task->created_at) === true
            && ! $task->steps()->where('done', true)->exists();
    }
}
