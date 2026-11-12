<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Enums\CalendarType;
use App\Enums\FamilyEventKind;
use App\Exceptions\HuiActionException;
use App\Livewire\Family\EventForm;
use App\Livewire\Family\EventIndex;
use App\Models\FamilyEvent;
use App\Models\User;
use App\Queries\FamilyAgenda;
use App\Queries\UserBackup;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
 * Ngày nhớ (document/09-gia-dinh.md GD-01, GD-02, GD-08).
 */

beforeEach(function () {
    $this->travelTo('2026-11-12 09:00:00');
});

test('both roles get a family area with its own navigation', function (User $user) {
    $this->actingAs($user);

    $this->get(route('family.index'))->assertOk()
        ->assertSeeInOrder(['Tổng', 'Hụi', 'Vàng', 'Sửa chữa', 'Gia đình', '12/11/2026 · 4/10 ÂL', 'Chưa có ngày nhớ nào', 'Ngày nhớ', 'Sức khỏe', 'Cài đặt'])
        ->assertDontSee('Dây hụi')
        ->assertDontSee('Món đồ');

    $this->get(route('settings'))->assertOk()->assertSee(route('family.health.index'));
})->with([
    'member' => fn () => User::factory()->create(),
    'host' => fn () => User::factory()->host()->create(),
]);

test('guests are sent to the login page', function () {
    $this->get(route('family.index'))->assertRedirect(route('login'));
    $this->get(route('family.health.index'))->assertRedirect(route('login'));
});

test('a lunar death anniversary is saved and previews its next date', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(EventForm::class)
        ->assertSet('calendar', 'am')
        ->set('title', ' Giỗ ông nội ')
        ->set('kind', 'gio')
        ->set('day', '10')
        ->set('month', '12')
        ->set('originYear', '2025')
        ->assertSee('17/01/2027')->assertSee('(10/12 ÂL) · Còn 66 ngày · Giỗ đầu')
        ->set('remindDaysBefore', '7')
        ->set('note', 'Làm mâm cơm')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('family.index'));

    $event = FamilyEvent::sole();
    expect($event->title)->toBe('Giỗ ông nội')
        ->and($event->kind)->toBe(FamilyEventKind::Gio)
        ->and($event->calendar)->toBe(CalendarType::Am)
        ->and([$event->day, $event->month, $event->origin_year, $event->remind_days_before])->toBe([10, 12, 2025, 7])
        ->and($event->user_id)->toBe($user->id);
});

test('an event can be edited and deleted', function () {
    $this->actingAs($user = User::factory()->create());
    $event = FamilyEvent::factory()->for($user)->create(['origin_year' => 1966]);

    Livewire::test(EventForm::class, ['familyEvent' => $event])
        ->assertSet('calendar', 'duong')
        ->assertSet('day', '20')
        ->assertSet('originYear', '1966')
        ->set('originYear', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->origin_year)->toBeNull();

    Livewire::test(EventForm::class, ['familyEvent' => $event])->call('delete')->assertRedirect(route('family.index'));
    expect(FamilyEvent::query()->count())->toBe(0);
});

test('impossible dates and bad input are rejected', function (array $input, string $errorField) {
    $this->actingAs(User::factory()->create());

    $form = Livewire::test(EventForm::class)->set('title', 'Sinh nhật')->set('calendar', 'duong')->set('day', '5')->set('month', '5');

    foreach ($input as $property => $value) {
        $form->set($property, $value);
    }

    $form->call('save')->assertHasErrors($errorField);
    expect(FamilyEvent::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ' '], 'title'],
    'no day' => [['day' => ''], 'day'],
    '31 April' => [['day' => '31', 'month' => '4'], 'day'],
    'lunar day 31' => [['calendar' => 'am', 'day' => '31', 'month' => '1'], 'day'],
    'month 13' => [['month' => '13'], 'month'],
    'remind too early' => [['remindDaysBefore' => '31'], 'remind_days_before'],
    'remind empty' => [['remindDaysBefore' => ''], 'remind_days_before'],
    'origin year not a number' => [['originYear' => 'abcd'], 'origin_year'],
    'unknown kind' => [['kind' => 'cuoi'], 'kind'],
]);

test('29 February is accepted for solar dates', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(EventForm::class)->set('title', 'Sinh nhật bé')->set('calendar', 'duong')->set('day', '29')->set('month', '2')
        ->assertSee('28/02/2027')
        ->call('save')->assertHasNoErrors();
});

test('the list is sorted by the next occurrence and shows countdowns', function () {
    $this->actingAs($user = User::factory()->create());
    FamilyEvent::factory()->for($user)->create(['title' => 'Kỷ niệm cưới', 'kind' => FamilyEventKind::KyNiem, 'day' => 1, 'month' => 11, 'origin_year' => 2016]);
    FamilyEvent::factory()->for($user)->create(['title' => 'Sinh nhật mẹ', 'day' => 13, 'month' => 11, 'origin_year' => 1966]);
    FamilyEvent::factory()->for($user)->lunar(4, 10)->create(['title' => 'Giỗ bà', 'kind' => FamilyEventKind::Gio, 'origin_year' => 2020]);

    Livewire::test(EventIndex::class)->assertSeeInOrder([
        'Giỗ bà', '12/11/2026 · 4/10 ÂL', 'Giỗ lần thứ 6', 'Hôm nay',
        'Sinh nhật mẹ', '13/11/2026', 'Tròn 60 tuổi', 'Ngày mai',
        'Kỷ niệm cưới', '01/11/2027', '11 năm', 'Còn 354 ngày',
    ]);
});

test('one account cannot see or change another account\'s events', function () {
    $this->actingAs($owner = User::factory()->create());
    $event = FamilyEvent::factory()->for($owner)->create(['title' => 'Bí mật']);

    $this->actingAs($other = User::factory()->create());

    $this->get(route('family.events.edit', $event->id))->assertNotFound();
    $this->get(route('family.index'))->assertDontSee('Bí mật');
    expect(app(FamilyAgenda::class)->upcoming($other, now()->toImmutable()))->toBe([]);
});

test('a backup carries the events into another account', function () {
    $this->actingAs($alice = User::factory()->create());
    FamilyEvent::factory()->for($alice)->lunar(30, 12)->create(['title' => 'Giỗ cụ', 'kind' => FamilyEventKind::Gio, 'origin_year' => 1990, 'remind_days_before' => 0, 'note' => 'Nhà bác cả']);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($alice)), true);

    $this->actingAs($bob = User::factory()->create());
    $stats = app(RestoreBackup::class)->handle($bob, $backup);

    expect($stats['family_events'])->toBe(1)
        ->and(app(UserBackup::class)->export($bob)['family'])->toBe($backup['family'])
        ->and(FamilyEvent::sole()->user_id)->toBe($bob->id);
});

test('restoring an older file without a family book leaves it alone', function () {
    $this->actingAs($user = User::factory()->create());
    $backup = json_decode(json_encode(app(UserBackup::class)->export($user)), true);
    unset($backup['family']);
    FamilyEvent::factory()->for($user)->create();

    app(RestoreBackup::class)->handle($user, $backup);

    expect(FamilyEvent::query()->count())->toBe(1);
});

test('a backup with an impossible date is rejected before anything is deleted', function () {
    $this->actingAs($user = User::factory()->create());
    FamilyEvent::factory()->for($user)->create(['day' => 30, 'month' => 4]);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($user)), true);
    $backup['family']['events'][0]['day'] = 31;

    expect(fn () => app(RestoreBackup::class)->handle($user, $backup))->toThrow(HuiActionException::class, 'không có thật');
    expect(FamilyEvent::sole()->day)->toBe(30);
});

test('the database refuses impossible lunar days and long reminders', function (array $attributes) {
    $this->actingAs($user = User::factory()->create());

    expect(fn () => FamilyEvent::factory()->for($user)->create($attributes))->toThrow(QueryException::class);
})->with([
    'lunar day 31' => [['calendar' => CalendarType::Am, 'day' => 31]],
    'month 0' => [['month' => 0]],
    'remind 31 days' => [['remind_days_before' => 31]],
]);
