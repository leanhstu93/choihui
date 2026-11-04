<?php

declare(strict_types=1);

use App\Domain\LunarCalendar;
use App\Enums\HuiFrequency;
use App\Livewire\Dashboard;
use App\Livewire\Groups\GroupForm;
use App\Livewire\Groups\GroupStatement;
use App\Livewire\Host\Dashboard as HostDashboard;
use App\Livewire\Host\GroupForm as HostGroupForm;
use App\Mail\DueReminderMail;
use App\Models\HuiGroup;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * @return list<string>
 */
function lunarLabelsOf(HuiGroup $group): array
{
    return $group->rounds()->get()
        ->map(fn ($round) => LunarCalendar::fromSolar($round->open_date)->label())
        ->all();
}

test('a member creates a lunar group by picking a lunar date', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GroupForm::class)
        ->set('name', 'Hụi rằm')
        ->set('faceValue', 1_000_000)
        ->set('totalSlots', 3)
        ->set('hostName', 'Cô Hoa')
        ->set('frequency', 'thang_am')
        ->set('lunarDay', 15)
        ->set('lunarMonth', '10')
        ->set('lunarYear', 2026)
        ->assertSee('Tức ngày dương 23/11/2026')
        ->call('save')
        ->assertHasNoErrors();

    $group = HuiGroup::sole();

    expect($group->frequency)->toBe(HuiFrequency::ThangAm)
        ->and($group->start_date->toDateString())->toBe('2026-11-23')
        ->and(lunarLabelsOf($group))->toBe(['15/10 ÂL', '15/11 ÂL', '15/12 ÂL']);
});

test('a host creates a lunar group too', function () {
    $this->actingAs(User::factory()->host()->create());
    $lan = Member::create(['name' => 'Chị Lan']);

    Livewire::test(HostGroupForm::class)
        ->set('name', 'Hụi rằm')
        ->set('faceValue', 1_000_000)
        ->set('totalSlots', 2)
        ->set('frequency', 'thang_am')
        ->set('lunarDay', 1)
        ->set('lunarMonth', '1')
        ->set('lunarYear', 2027)
        ->set('memberIds.0', $lan->id)
        ->set('memberIds.1', $lan->id)
        ->call('save')
        ->assertHasNoErrors();

    $group = HuiGroup::sole();

    expect($group->start_date->toDateString())->toBe('2027-02-06')
        ->and(lunarLabelsOf($group))->toBe(['1/1 ÂL', '1/2 ÂL']);
});

test('solar groups stay on the solar schedule by default', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GroupForm::class)
        ->set('name', 'Hụi dương')
        ->set('faceValue', 1_000_000)
        ->set('totalSlots', 2)
        ->set('hostName', 'Cô Hoa')
        ->set('startDate', '2026-01-31')
        ->call('save')
        ->assertHasNoErrors();

    expect(HuiGroup::sole()->frequency)->toBe(HuiFrequency::Thang)
        ->and(HuiGroup::sole()->rounds()->get()->map->open_date->map->toDateString()->all())
        ->toBe(['2026-01-31', '2026-02-28']);
});

test('the leap month appears only in a year that has it', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(GroupForm::class)
        ->set('frequency', 'thang_am')
        ->set('lunarYear', 2028)
        ->assertSee('Tháng 5 nhuận')
        ->set('lunarMonth', '5n')
        ->set('lunarYear', 2026)
        ->assertDontSee('Tháng 5 nhuận')
        ->assertSet('lunarMonth', '5');

    expect($component->get('lunarMonth'))->toBe('5');
});

test('a lunar date that does not exist is rejected in Vietnamese', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GroupForm::class)
        ->set('name', 'Hụi')
        ->set('faceValue', 1_000_000)
        ->set('totalSlots', 2)
        ->set('hostName', 'Cô Hoa')
        ->set('frequency', 'thang_am')
        ->set('lunarYear', 1800)
        ->call('save')
        ->assertHasErrors(['start_date']);

    expect(HuiGroup::count())->toBe(0);
});

test('lunar dates show next to solar dates, solar groups are unchanged', function () {
    $this->actingAs(User::factory()->create());
    $lunar = createHuiGroup(['frequency' => 'thang_am', 'start_date' => '2026-11-23']);
    $solar = createHuiGroup(['name' => 'Hụi dương', 'start_date' => '2026-11-23']);

    $this->get(route('groups.show', $lunar))->assertSee('23/11/2026 (15/10 ÂL)')->assertSee('Hằng tháng âm lịch');
    $this->get(route('rounds.show', $lunar->rounds[0]))->assertSee('mở 23/11/2026 (15/10 ÂL)');
    $this->get(route('groups.show', $solar))->assertDontSee('ÂL')->assertDontSee('Hằng tháng âm lịch');
    Livewire::test(GroupStatement::class, ['group' => $lunar])->assertSee('15/10 ÂL');
});

test('the dashboards and reminder email show the lunar due date', function () {
    $this->travelTo('2026-11-20');
    Mail::fake();

    $member = User::factory()->create(['reminder_days_before' => 3]);
    $this->actingAs($member);
    createHuiGroup(['frequency' => 'thang_am', 'start_date' => '2026-11-23']);
    Livewire::test(Dashboard::class)->assertSee('23/11/2026 (15/10 ÂL)');

    $this->artisan('hui:send-due-reminders', ['--date' => '2026-11-20']);
    Mail::assertSent(DueReminderMail::class, function (DueReminderMail $mail) {
        $mail->assertSeeInHtml('23/11/2026 (15/10 ÂL)');

        return true;
    });

    $this->actingAs(User::factory()->host()->create());
    $hostGroup = createHostGroup(['frequency' => 'thang_am', 'start_date' => '2026-11-19', 'total_slots' => 2]);
    Livewire::test(HostDashboard::class)->assertSee($hostGroup->lunarLabel($hostGroup->start_date));
});
