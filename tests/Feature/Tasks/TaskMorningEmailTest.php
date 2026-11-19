<?php

declare(strict_types=1);

use App\Livewire\Settings\SettingsPage;
use App\Mail\FamilyReminderMail;
use App\Models\FamilyEvent;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Email buổi sáng và dọn việc cũ (document/10-viec-can-lam.md V13, V14, VL-09, VL-10).
 * Tạo dữ liệu khi chưa đăng nhập: OwnedByUser giữ nguyên user_id của factory.
 */

beforeEach(function () {
    Mail::fake();
});

test('overdue and today tasks go into the morning email, overdue first', function () {
    $user = User::factory()->create();
    Task::factory()->for($user)->due('2026-11-18', '08:00')->create(['title' => 'Đóng tiền điện']);
    Task::factory()->for($user)->due('2026-11-16')->important()->create(['title' => 'Nộp thuế']);
    Task::factory()->for($user)->due('2026-11-19')->create(['title' => 'Ngày mai']);
    Task::factory()->for($user)->due('2026-11-17')->done()->create(['title' => 'Đã xong']);

    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();

    Mail::assertSent(FamilyReminderMail::class, function (FamilyReminderMail $mail) {
        $html = $mail->render();

        return array_map(fn (Task $t) => $t->title, $mail->tasks) === ['Nộp thuế', 'Đóng tiền điện']
            && $mail->envelope()->subject === 'Việc hôm nay: Nộp thuế và 1 việc khác'
            && str_contains($html, 'Trễ 2 ngày')
            && str_contains($html, 'Hôm nay 08:00')
            && str_contains($html, route('tasks.index'));
    });
    expect($user->fresh()?->family_reminded_on?->toDateString())->toBe('2026-11-18');
});

test('tasks share the email with family reminders', function () {
    $user = User::factory()->create();
    FamilyEvent::factory()->for($user)->create(['title' => 'Sinh nhật mẹ', 'day' => 21, 'month' => 11]);
    Task::factory()->for($user)->due('2026-11-18')->create();

    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(FamilyReminderMail::class, fn (FamilyReminderMail $mail) => $mail->envelope()->subject === 'Nhắc: Sinh nhật mẹ (còn 3 ngày) và 1 việc khác');
});

test('an account that opted out still gets family reminders, without tasks', function () {
    $user = User::factory()->create(['tasks_in_morning_email' => false]);
    FamilyEvent::factory()->for($user)->create(['day' => 21, 'month' => 11]);
    Task::factory()->for($user)->due('2026-11-18')->create();

    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();

    Mail::assertSent(FamilyReminderMail::class, fn (FamilyReminderMail $mail) => $mail->events !== [] && $mail->tasks === []);
});

test('the morning email lists at most 10 tasks', function () {
    $user = User::factory()->create();
    Task::factory()->count(12)->for($user)->due('2026-11-18')->create();

    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();

    Mail::assertSent(FamilyReminderMail::class, fn (FamilyReminderMail $mail) => str_contains($mail->render(), 'và 2 việc khác'));
});

test('nothing is sent without due tasks, to accounts that opted out, or twice a day', function () {
    $optedOut = User::factory()->create(['tasks_in_morning_email' => false]);
    Task::factory()->for($optedOut)->due('2026-11-18')->create();
    $future = User::factory()->create();
    Task::factory()->for($future)->due('2026-11-19')->create();
    Task::factory()->for($future)->create();

    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();
    Mail::assertNothingSent();

    $user = User::factory()->create();
    Task::factory()->for($user)->due('2026-11-18')->create();
    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();
    $this->artisan('family:send-reminders', ['--date' => '2026-11-18'])->assertSuccessful();

    Mail::assertSentCount(1);
});

test('the settings switch turns the tasks part of the morning email on and off', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(SettingsPage::class)
        ->assertSee('Việc cần làm trong email buổi sáng')
        ->assertSet('tasksInMorningEmail', true)
        ->set('tasksInMorningEmail', false);

    expect($user->fresh()?->tasks_in_morning_email)->toBeFalse();
});

test('done tasks older than 90 days are pruned for every account, with their steps', function () {
    $this->travelTo('2026-11-18 03:00:00');
    $old = Task::factory()->done('2026-08-19 02:00:00')->create(['title' => 'Cũ']);
    TaskStep::factory()->for($old)->create();
    Task::factory()->done('2026-08-20 10:00:00')->create(['title' => 'Vừa đủ 90 ngày']);
    Task::factory()->due('2026-01-01')->create(['title' => 'Chưa xong, rất cũ']);

    $this->artisan('tasks:prune')->expectsOutputToContain('Đã xóa 1 việc')->assertSuccessful();

    expect(Task::withoutGlobalScopes()->orderBy('title')->pluck('title')->all())->toBe(['Chưa xong, rất cũ', 'Vừa đủ 90 ngày'])
        ->and(TaskStep::withoutGlobalScopes()->count())->toBe(0);
});
