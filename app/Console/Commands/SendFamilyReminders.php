<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\FamilyReminderMail;
use App\Models\User;
use App\Queries\FamilyAgenda;
use App\Queries\TaskAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Gửi email buổi sáng: ngày nhớ, lịch tái khám (document/09-gia-dinh.md G6, G7, G10) và việc quá hạn /
 * hôm nay (document/10-viec-can-lam.md V14). Chạy hằng ngày qua Scheduler, không có ai đăng nhập: mọi
 * truy vấn lọc user_id tường minh qua FamilyAgenda và TaskAgenda.
 */
#[Signature('family:send-reminders {--date= : Ngày chạy (Y-m-d), mặc định hôm nay}')]
#[Description('Gửi email buổi sáng: giỗ, sinh nhật, kỷ niệm, lịch tái khám và việc cần làm hôm nay')]
class SendFamilyReminders extends Command
{
    public function handle(FamilyAgenda $agenda, TaskAgenda $taskAgenda): int
    {
        $today = CarbonImmutable::parse($this->option('date') ?: 'today')->startOfDay();
        $sentCount = 0;

        User::query()
            ->whereNull('locked_at')
            ->where(fn ($query) => $query->whereNull('family_reminded_on')->orWhere('family_reminded_on', '<', $today->toDateString()))
            ->where(fn ($query) => $query
                ->whereExists(fn ($events) => $events->selectRaw('1')->from('family_events')->whereColumn('family_events.user_id', 'users.id')->where('remind_days_before', '>', 0))
                ->orWhereExists(fn ($records) => $records->selectRaw('1')->from('health_records')->whereColumn('health_records.user_id', 'users.id')->where('follow_up_on', '>=', $today->toDateString()))
                ->orWhere(fn ($withTasks) => $withTasks
                    ->where('tasks_in_morning_email', true)
                    ->whereExists(fn ($tasks) => $tasks->selectRaw('1')->from('tasks')->whereColumn('tasks.user_id', 'users.id')->whereNull('completed_at')->where('due_on', '<=', $today->toDateString()))))
            ->lazyById()
            ->each(function (User $user) use ($agenda, $taskAgenda, $today, &$sentCount): void {
                $reminders = $agenda->remindersFor($user, $today);
                $tasks = $user->tasks_in_morning_email ? $taskAgenda->dueForEmail($user, $today) : [];

                if ($reminders['events'] === [] && $reminders['followUps']->isEmpty() && $tasks === []) {
                    return;
                }

                try {
                    Mail::to($user)->send(new FamilyReminderMail($user, $reminders['events'], $reminders['followUps'], $today, $tasks));
                    $user->forceFill(['family_reminded_on' => $today->toDateString()])->save();
                    $sentCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Không gửi được cho user #{$user->id}: {$exception->getMessage()}");
                }
            });

        $this->info("Đã gửi {$sentCount} email buổi sáng.");

        return self::SUCCESS;
    }
}
