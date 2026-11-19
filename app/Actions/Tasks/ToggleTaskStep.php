<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\TaskStep;

/**
 * Tích / bỏ tích một bước nhỏ (document/10-viec-can-lam.md V5).
 */
final class ToggleTaskStep
{
    /**
     * @return bool Mọi bước của việc đã xong hay chưa, để gợi ý đánh dấu xong việc
     */
    public function handle(TaskStep $step, bool $done): bool
    {
        $step->update(['done' => $done]);

        return ! $step->task->steps()->where('done', false)->exists();
    }
}
