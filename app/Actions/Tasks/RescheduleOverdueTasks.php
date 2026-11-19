<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\Task;

/**
 * "Dời hết việc quá hạn sang hôm nay" (document/10-viec-can-lam.md V9).
 */
final class RescheduleOverdueTasks
{
    /**
     * @return int Số việc đã dời
     */
    public function handle(): int
    {
        $today = now()->toDateString();

        return Task::query()->open()->where('due_on', '<', $today)->update(['due_on' => $today, 'updated_at' => now()]);
    }
}
