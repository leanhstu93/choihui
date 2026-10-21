<?php

declare(strict_types=1);

use App\Actions\Payments\MarkPaid;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\WinnerType;
use App\Mail\DueReminderMail;
use App\Models\HuiGroup;
use App\Models\User;
use App\Queries\UpcomingPayment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/**
 * Tạo một dây cho người dùng (kỳ 1 mở ngày $startDate, hạn đóng cùng ngày) rồi đăng xuất.
 */
function groupDueOn(User $user, string $startDate, string $name = 'Hụi cô Hoa'): HuiGroup
{
    Auth::login($user);
    $group = createHuiGroup(['name' => $name, 'start_date' => $startDate]);
    Auth::logout();

    return $group;
}

test('each user only receives their own payments when no one is signed in (case 5)', function () {
    $alice = User::factory()->create(['reminder_days_before' => 3]);
    $bob = User::factory()->create(['reminder_days_before' => 3]);
    groupDueOn($alice, '2026-03-08', 'Dây của Alice');
    groupDueOn($bob, '2026-03-08', 'Dây của Bob');

    $this->artisan('hui:send-due-reminders', ['--date' => '2026-03-05'])->assertSuccessful();

    expect(Auth::check())->toBeFalse();
    Mail::assertSentCount(2);
    Mail::assertSent(DueReminderMail::class, function (DueReminderMail $mail) use ($alice) {
        $groupNames = array_unique(array_map(fn (UpcomingPayment $payment) => $payment->groupName, $mail->payments));

        return $mail->hasTo($alice->email) && $groupNames === ['Dây của Alice'];
    });
    Mail::assertSent(DueReminderMail::class, function (DueReminderMail $mail) use ($bob) {
        $groupNames = array_unique(array_map(fn (UpcomingPayment $payment) => $payment->groupName, $mail->payments));

        return $mail->hasTo($bob->email) && $groupNames === ['Dây của Bob'];
    });
});

test('reminders are sent N days before and on the due date only', function (string $date, bool $expectSent) {
    $user = User::factory()->create(['reminder_days_before' => 3]);
    groupDueOn($user, '2026-03-08');

    $this->artisan('hui:send-due-reminders', ['--date' => $date])->assertSuccessful();

    $expectSent ? Mail::assertSent(DueReminderMail::class) : Mail::assertNothingSent();
})->with([
    '3 days before' => ['2026-03-05', true],
    '2 days before' => ['2026-03-06', false],
    'on the due date' => ['2026-03-08', true],
    '4 days before' => ['2026-03-04', false],
]);

test('a reminder is sent at most once per day', function () {
    $user = User::factory()->create(['reminder_days_before' => 3]);
    groupDueOn($user, '2026-03-08');

    $this->artisan('hui:send-due-reminders', ['--date' => '2026-03-05']);
    $this->artisan('hui:send-due-reminders', ['--date' => '2026-03-05']);

    Mail::assertSentCount(1);
    expect($user->refresh()->last_reminded_on->toDateString())->toBe('2026-03-05');
});

test('users who turned reminders off receive nothing', function () {
    $user = User::factory()->create(['reminder_days_before' => 0]);
    groupDueOn($user, '2026-03-05');

    $this->artisan('hui:send-due-reminders', ['--date' => '2026-03-05']);

    Mail::assertNothingSent();
});

test('paid entries are not reminded', function () {
    $user = User::factory()->create(['reminder_days_before' => 3]);
    $group = groupDueOn($user, '2026-03-08');
    Auth::login($user);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($group->rounds[0]->cashEntries()->sole(), 900_000, new DateTimeImmutable('2026-03-01'));
    Auth::logout();

    $this->artisan('hui:send-due-reminders', ['--date' => '2026-03-08']);

    Mail::assertNothingSent();
});

test('the reminder email lists the payments with Vietnamese formatting', function () {
    $user = User::factory()->create(['name' => 'Anh Lê', 'reminder_days_before' => 3]);
    $group = groupDueOn($user, '2026-03-08');
    Auth::login($user);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
    Auth::logout();

    $this->artisan('hui:send-due-reminders', ['--date' => '2026-03-05']);

    Mail::assertSent(DueReminderMail::class, function (DueReminderMail $mail) {
        $mail->assertHasSubject('Nhắc đóng hụi: 900.000 đ sắp đến hạn');
        $mail->assertSeeInHtml('Chào Anh Lê');
        $mail->assertSeeInHtml('08/03/2026');
        $mail->assertSeeInHtml('Kỳ 1 · Tay 1');

        return true;
    });
});

test('the reminder is scheduled daily at 7am Vietnam time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'hui:send-due-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 7 * * *')
        ->and($event->timezone)->toBe('Asia/Ho_Chi_Minh');
});
