<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DueReminderMail;
use App\Models\User;
use App\Queries\UpcomingPayment;
use App\Queries\UpcomingPayments;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Gửi email nhắc đóng hụi (FR-09). Chạy hằng ngày qua Scheduler, không có ai đăng nhập:
 * mọi truy vấn lọc user_id tường minh qua UpcomingPayments (document/01-dac-ta.md mục 8, ca 5).
 *
 * Mỗi khoản được nhắc hai lần: trước hạn đúng N ngày (cài đặt của từng người) và vào
 * ngày đến hạn. Email liệt kê mọi khoản từ nay đến N ngày tới, kể cả khoản quá hạn.
 */
#[Signature('hui:send-due-reminders {--date= : Ngày chạy (Y-m-d), mặc định hôm nay}')]
#[Description('Gửi email nhắc các khoản hụi sắp đến hạn')]
class SendDueReminders extends Command
{
    public function handle(UpcomingPayments $upcomingPayments): int
    {
        $today = new DateTimeImmutable($this->option('date') ?: 'today');
        $sentCount = 0;

        User::query()
            ->where('reminder_days_before', '>', 0)
            ->whereNull('locked_at')
            ->where(fn ($query) => $query->whereNull('last_reminded_on')->orWhere('last_reminded_on', '<', $today->format('Y-m-d')))
            ->lazyById()
            ->each(function (User $user) use ($upcomingPayments, $today, &$sentCount): void {
                $until = $today->modify("+{$user->reminder_days_before} days");
                $payments = $upcomingPayments->forUser($user, $until);

                if (! $this->hasTrigger($payments, $today, $until)) {
                    return;
                }

                try {
                    Mail::to($user)->send(new DueReminderMail($user, $payments, $today));
                    $user->forceFill(['last_reminded_on' => $today->format('Y-m-d')])->save();
                    $sentCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Không gửi được cho user #{$user->id}: {$exception->getMessage()}");
                }
            });

        $this->info("Đã gửi {$sentCount} email nhắc.");

        return self::SUCCESS;
    }

    /**
     * Chỉ gửi vào ngày có khoản đến hạn hôm nay hoặc đúng N ngày nữa.
     *
     * @param  list<UpcomingPayment>  $payments
     */
    private function hasTrigger(array $payments, DateTimeImmutable $today, DateTimeImmutable $until): bool
    {
        $triggerDates = [$today->format('Y-m-d'), $until->format('Y-m-d')];

        foreach ($payments as $payment) {
            if (in_array($payment->dueDate->format('Y-m-d'), $triggerDates, true)) {
                return true;
            }
        }

        return false;
    }
}
