<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Exceptions\HuiActionException;
use App\Models\Task;
use App\Models\TaskStep;

/**
 * Thêm hoặc đổi tên một bước nhỏ (document/10-viec-can-lam.md V5).
 */
final class SaveTaskStep
{
    public function handle(Task $task, ?TaskStep $step, string $title): TaskStep
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 200) {
            throw new HuiActionException('Tên bước cần từ 1 đến 200 ký tự.');
        }

        if ($step !== null) {
            $step->update(['title' => $title]);

            return $step;
        }

        if ($task->steps()->count() >= Task::MAX_STEPS) {
            throw new HuiActionException('Mỗi việc có tối đa '.Task::MAX_STEPS.' bước. Tách bớt thành việc riêng nhé.');
        }

        return $task->steps()->create([
            'title' => $title,
            'position' => (int) $task->steps()->max('position') + 1,
        ]);
    }
}
