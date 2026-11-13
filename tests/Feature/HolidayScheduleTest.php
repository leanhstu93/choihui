<?php

declare(strict_types=1);

use App\Enums\AdminAction;
use App\Enums\HolidayBreakKind;
use App\Livewire\Admin\HolidayIndex;
use App\Mail\HolidayScheduleReminderMail;
use App\Models\AdminLog;
use App\Models\OfficialHolidayBreak;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Lịch nghỉ lễ: quản trị nhập lịch chính thức, lịch âm hiện ngày được nghỉ, email nhắc quản trị.
 */

beforeEach(function () {
    config(['hui.admin_emails' => ['admin@example.com']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00'));
});

function holidayAdmin(): User
{
    return User::factory()->create(['email' => 'admin@example.com']);
}

test('the 2027 Tết and national day schedules announced by the ministry are already official', function () {
    $breaks = collect($this->getJson(route('lunar.data', 2027))->assertOk()->json('breaks'));

    expect($breaks->firstWhere('kind', 'tet_nguyen_dan'))->toMatchArray(['start' => '2027-02-04', 'end' => '2027-02-10', 'length' => 7, 'official' => true])
        ->and($breaks->firstWhere('kind', 'quoc_khanh'))->toMatchArray(['start' => '2027-09-02', 'end' => '2027-09-05', 'official' => true])
        ->and($breaks->firstWhere('kind', 'gio_to'))->toMatchArray(['official' => false]);
});

test('day pages say whether people get the day off', function () {
    $this->get(route('lunar.day', '2027-02-09'))->assertOk()
        ->assertSee('Ngày này được nghỉ làm: nghỉ bù Tết Nguyên đán (lịch chính thức, 04/02 – 10/02/2027).');
    $this->get(route('lunar.day', '2026-11-24'))->assertOk()
        ->assertSee('Ngày này được nghỉ làm: nghỉ lễ Ngày Văn hóa Việt Nam (lịch dự kiến');
    $this->get(route('lunar.day', '2026-11-20'))->assertOk()->assertSee('Ngày đi làm bình thường.');
    $this->get(route('lunar.year', 2027))->assertOk()->assertSee('Lịch nghỉ lễ năm 2027')->assertSee('Thông báo 11483/TB-BNV');
});

test('only admins open the holiday schedule page', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.holidays'))->assertNotFound();
    $this->actingAs(holidayAdmin())->get(route('admin.holidays'))->assertOk()->assertSee('Lịch nghỉ lễ')->assertSee('Dự kiến');
});

test('an admin enters an official schedule, prefilled from the estimate, and can revert it', function () {
    $this->actingAs($admin = holidayAdmin());

    Livewire::test(HolidayIndex::class)
        ->call('selectYear', 2028)
        ->call('edit', 'tet_nguyen_dan')
        ->assertSet('form.starts_on', '2028-01-22')
        ->assertSet('form.ends_on', '2028-01-30')
        ->set('form.starts_on', '2028-01-24')
        ->set('form.ends_on', '2028-02-01')
        ->set('form.make_up_on', '2028-02-05')
        ->set('form.source', 'Thông báo thử')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', null)
        ->assertSee('Chính thức');

    $entry = OfficialHolidayBreak::query()->where('year', 2028)->where('kind', HolidayBreakKind::TetNguyenDan)->sole();
    expect($entry->starts_on->toDateString())->toBe('2028-01-24')
        ->and($entry->make_up_on?->toDateString())->toBe('2028-02-05')
        ->and(AdminLog::query()->where('action', AdminAction::SaveHolidayBreak)->where('admin_id', $admin->id)->exists())->toBeTrue();

    $this->getJson(route('lunar.data', 2028))->assertJsonFragment(['makeUpOn' => '2028-02-05', 'official' => true]);

    Livewire::test(HolidayIndex::class)->call('selectYear', 2028)->call('revert', 'tet_nguyen_dan');
    expect(OfficialHolidayBreak::query()->where('year', 2028)->exists())->toBeFalse()
        ->and(AdminLog::query()->where('action', AdminAction::DeleteHolidayBreak)->exists())->toBeTrue();
});

test('the official schedule must be a sensible range', function () {
    $this->actingAs(holidayAdmin());

    Livewire::test(HolidayIndex::class)
        ->call('edit', 'quoc_khanh')
        ->set('form.starts_on', '2026-09-05')
        ->set('form.ends_on', '2026-09-01')
        ->call('save')
        ->assertHasErrors(['ends_on'])
        ->set('form.ends_on', '2026-09-30')
        ->call('save')
        ->assertHasErrors(['ends_on']);

    expect(OfficialHolidayBreak::query()->where('year', 2026)->exists())->toBeFalse();
});

test('admins are reminded from October until next year Tết schedule is entered', function () {
    Mail::fake();

    $this->artisan('holidays:remind-admin', ['--date' => '2026-09-28'])->assertSuccessful();
    Mail::assertNothingSent();

    $this->artisan('holidays:remind-admin', ['--date' => '2027-10-04'])->assertSuccessful();
    Mail::assertSent(HolidayScheduleReminderMail::class, fn (HolidayScheduleReminderMail $mail) => $mail->year === 2028 && $mail->hasTo('admin@example.com'));

    // Tết 2027 đã có lịch chính thức.
    Mail::fake();
    $this->artisan('holidays:remind-admin', ['--date' => '2026-10-05'])->assertSuccessful();
    Mail::assertNothingSent();

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'holidays:remind-admin'));
    expect($event->expression)->toBe('0 8 * * 1')->and($event->timezone)->toBe('Asia/Ho_Chi_Minh');
});
