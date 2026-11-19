<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Xóa việc đã xong quá 90 ngày, của mọi tài khoản (document/10-viec-can-lam.md V13). Chạy nền nên bỏ
 * global scope; các bước nhỏ bị xóa theo (cascade). Lần tới mà việc cũ trỏ tới không bị đụng.
 */
#[Signature('tasks:prune')]
#[Description('Xóa việc đã xong quá 90 ngày')]
class PruneDoneTasks extends Command
{
    public function handle(): int
    {
        $count = Task::query()
            ->withoutGlobalScopes()
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', now()->subDays(Task::KEEP_DONE_DAYS))
            ->delete();

        $this->info("Đã xóa {$count} việc xong quá ".Task::KEEP_DONE_DAYS.' ngày.');

        return self::SUCCESS;
    }
}
