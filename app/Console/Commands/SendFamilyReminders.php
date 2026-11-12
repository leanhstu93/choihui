<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\FamilyReminderMail;
use App\Models\User;
use App\Queries\FamilyAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Gửi email nhắc ngày nhớ và lịch tái khám (document/09-gia-dinh.md G6, G7, G10). Chạy hằng ngày
 * qua Scheduler, không có ai đăng nhập: mọi truy vấn lọc user_id tường minh qua FamilyAgenda.
 */
#[Signature('family:send-reminders {--date= : Ngày chạy (Y-m-d), mặc định hôm nay}')]
#[Description('Gửi email nhắc giỗ, sinh nhật, ngày kỷ niệm và lịch tái khám')]
class SendFamilyReminders extends Command
{
    public function handle(FamilyAgenda $agenda): int
    {
        $today = CarbonImmutable::parse($this->option('date') ?: 'today')->startOfDay();
        $sentCount = 0;

        User::query()
            ->whereNull('locked_at')
            ->where(fn ($query) => $query->whereNull('family_reminded_on')->orWhere('family_reminded_on', '<', $today->toDateString()))
            ->where(fn ($query) => $query
                ->whereExists(fn ($events) => $events->selectRaw('1')->from('family_events')->whereColumn('family_events.user_id', 'users.id')->where('remind_days_before', '>', 0))
                ->orWhereExists(fn ($records) => $records->selectRaw('1')->from('health_records')->whereColumn('health_records.user_id', 'users.id')->where('follow_up_on', '>=', $today->toDateString())))
            ->lazyById()
            ->each(function (User $user) use ($agenda, $today, &$sentCount): void {
                $reminders = $agenda->remindersFor($user, $today);

                if ($reminders['events'] === [] && $reminders['followUps']->isEmpty()) {
                    return;
                }

                try {
                    Mail::to($user)->send(new FamilyReminderMail($user, $reminders['events'], $reminders['followUps'], $today));
                    $user->forceFill(['family_reminded_on' => $today->toDateString()])->save();
                    $sentCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Không gửi được cho user #{$user->id}: {$exception->getMessage()}");
                }
            });

        $this->info("Đã gửi {$sentCount} email nhắc gia đình.");

        return self::SUCCESS;
    }
}
