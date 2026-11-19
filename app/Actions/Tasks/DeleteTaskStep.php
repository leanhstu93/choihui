<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\TaskStep;

/**
 * Xóa một bước nhỏ (document/10-viec-can-lam.md V5).
 */
final class DeleteTaskStep
{
    public function handle(TaskStep $step): void
    {
        $step->delete();
    }
}
