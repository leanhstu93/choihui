<?php

declare(strict_types=1);

use App\Enums\FamilyEventKind;
use App\Mail\FamilyReminderMail;
use App\Models\FamilyEvent;
use App\Models\HealthPerson;
use App\Models\HealthRecord;
use App\Models\User;
use App\Queries\FamilyAgenda;
use App\Queries\FamilyOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/*
 * Email nhắc ngày nhớ và tái khám (document/09-gia-dinh.md G6, G7, G10).
 */

beforeEach(function () {
    Mail::fake();
});

/**
 * Tạo dữ liệu dưới tên $user rồi đăng xuất, như khi lệnh chạy nền.
 */
function asOwner(User $user, Closure $create): mixed
{
    Auth::login($user);
    $result = $create();
    Auth::logout();

    return $result;
}

test('each user only receives their own events when no one is signed in', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    asOwner($alice, fn () => FamilyEvent::factory()->for($alice)->create(['title' => 'Sinh nhật Alice', 'day' => 15, 'month' => 3]));
    asOwner($bob, fn () => FamilyEvent::factory()->for($bob)->create(['title' => 'Sinh nhật Bob', 'day' => 15, 'month' => 3]));

    $this->artisan('family:send-reminders', ['--date' => '2026-03-12'])->assertSuccessful();

    expect(Auth::check())->toBeFalse();
    Mail::assertSentCount(2);
    foreach ([[$alice, 'Sinh nhật Alice'], [$bob, 'Sinh nhật Bob']] as [$user, $title]) {
        Mail::assertSent(FamilyReminderMail::class, fn (FamilyReminderMail $mail) => $mail->hasTo($user->email)
            && array_map(fn (FamilyOccurrence $occurrence) => $occurrence->event->title, $mail->events) === [$title]);
    }
});

test('events are reminded N days before and on the day only', function (string $date, bool $expectSent) {
    $user = User::factory()->create();
    asOwner($user, fn () => FamilyEvent::factory()->for($user)->create(['day' => 15, 'month' => 3, 'remind_days_before' => 3]));

    $this->artisan('family:send-reminders', ['--date' => $date])->assertSuccessful();

    $expectSent ? Mail::assertSent(FamilyReminderMail::class) : Mail::assertNothingSent();
})->with([
    '3 days before' => ['2026-03-12', true],
    '2 days before' => ['2026-03-13', false],
    'on the day' => ['2026-03-15', true],
    'the day after' => ['2026-03-16', false],
]);

test('a lunar death anniversary is reminded on the right solar day with its years', function () {
    $user = User::factory()->create();
    asOwner($user, fn () => FamilyEvent::factory()->for($user)->lunar(10, 12)->create(['title' => 'Giỗ ông', 'kind' => FamilyEventKind::Gio, 'origin_year' => 2025, 'remind_days_before' => 7]));

    $this->artisan('family:send-reminders', ['--date' => '2027-01-10'])->assertSuccessful();

    Mail::assertSent(FamilyReminderMail::class, function (FamilyReminderMail $mail) {
        $occurrence = $mail->events[0];

        return $occurrence->date->toDateString() === '2027-01-17'
            && $occurrence->yearsLabel === 'Giỗ đầu'
            && $mail->envelope()->subject === 'Nhắc: Giỗ ông (còn 7 ngày)';
    });
});

test('events set to 0 days are never reminded', function () {
    $user = User::factory()->create();
    asOwner($user, fn () => FamilyEvent::factory()->for($user)->create(['day' => 15, 'month' => 3, 'remind_days_before' => 0]));

    $this->artisan('family:send-reminders', ['--date' => '2026-03-15'])->assertSuccessful();

    Mail::assertNothingSent();
});

test('follow-ups are reminded the day before and on the day, in the same email as events', function () {
    $user = User::factory()->create();
    asOwner($user, function () use ($user) {
        FamilyEvent::factory()->for($user)->create(['title' => 'Sinh nhật mẹ', 'day' => 20, 'month' => 3]);
        $person = HealthPerson::factory()->for($user)->create(['name' => 'Bố']);
        HealthRecord::factory()->create(['health_person_id' => $person->id, 'visited_on' => '2026-02-18', 'title' => 'Khám tim', 'follow_up_on' => '2026-03-18']);
    });

    $this->artisan('family:send-reminders', ['--date' => '2026-03-16'])->assertSuccessful();
    Mail::assertNothingSent();

    $this->artisan('family:send-reminders', ['--date' => '2026-03-17'])->assertSuccessful();
    Mail::assertSent(FamilyReminderMail::class, fn (FamilyReminderMail $mail) => $mail->events !== []
        && $mail->followUps->pluck('title')->all() === ['Khám tim']
        && $mail->envelope()->subject === 'Nhắc: Sinh nhật mẹ (còn 3 ngày) và 1 việc khác');
});

test('a user is reminded at most once a day and locked accounts are skipped', function () {
    $user = User::factory()->create();
    $locked = User::factory()->create(['locked_at' => now()]);
    foreach ([$user, $locked] as $owner) {
        asOwner($owner, fn () => FamilyEvent::factory()->for($owner)->create(['day' => 15, 'month' => 3]));
    }

    $this->artisan('family:send-reminders', ['--date' => '2026-03-15'])->assertSuccessful();
    $this->artisan('family:send-reminders', ['--date' => '2026-03-15'])->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(FamilyReminderMail::class, fn (FamilyReminderMail $mail) => $mail->hasTo($user->email));
    expect($user->refresh()->family_reminded_on->toDateString())->toBe('2026-03-15');
});

test('the email renders events and follow-ups', function () {
    $user = User::factory()->create();
    asOwner($user, function () use ($user) {
        FamilyEvent::factory()->for($user)->lunar(15, 1)->create(['title' => 'Giỗ bà', 'kind' => FamilyEventKind::Gio, 'origin_year' => 2020, 'remind_days_before' => 0]);
        HealthRecord::factory()->create(['health_person_id' => HealthPerson::factory()->for($user)->create(['name' => 'Bé Na'])->id, 'visited_on' => '2026-02-03', 'title' => 'Khám răng', 'follow_up_on' => '2026-03-03']);
    });

    Auth::login($user);
    $agenda = app(FamilyAgenda::class);
    $today = CarbonImmutable::parse('2026-03-03');
    $occurrence = $agenda->occurrenceOf(FamilyEvent::sole(), $today);
    $mail = new FamilyReminderMail($user, [$occurrence], $agenda->followUps($user, $today, $today), $today);

    $mail->assertSeeInOrderInHtml(['Giỗ bà', 'Giỗ lần thứ 6', 'Hôm nay', 'Lịch tái khám', '03/03/2026 (hôm nay)', 'Bé Na', 'Khám răng']);
});

test('the reminder runs daily at 07:00 Vietnam time', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'family:send-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 7 * * *')
        ->and($event->timezone)->toBe('Asia/Ho_Chi_Minh');
});
