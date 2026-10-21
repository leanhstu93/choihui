<?php

declare(strict_types=1);

use App\Actions\Rounds\RecordRoundResult;
use App\Enums\CashEntryStatus;
use App\Enums\WinnerType;
use App\Livewire\Dashboard;
use App\Livewire\Settings\SettingsPage;
use App\Livewire\Settings\UnlockPage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the dashboard shows the total due and pays an entry in one tap', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['name' => 'Hụi cô Hoa', 'start_date' => now()->toDateString()]);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
    $entry = $group->rounds[0]->cashEntries()->sole();

    Livewire::test(Dashboard::class)
        ->assertSee('Hụi cô Hoa')
        ->assertSee('900.000 đ')
        ->call('payInFull', $entry->id)
        ->assertDontSee('Kỳ 1 · Tay 1');

    expect($entry->refresh()->status)->toBe(CashEntryStatus::Du);
});

test('the dashboard cannot pay another user\'s entry', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
    $entry = $group->rounds[0]->cashEntries()->sole();

    $this->actingAs(User::factory()->create());

    expect(fn () => Livewire::test(Dashboard::class)->call('payInFull', $entry->id))
        ->toThrow(ModelNotFoundException::class)
        ->and($entry->refresh()->amount_actual)->toBe(0);
});

test('the main pages render', function (string $routeName) {
    $this->actingAs(User::factory()->create());
    createHuiGroup();

    $this->get(route($routeName))->assertOk();
})->with(['dashboard', 'groups.index', 'reports', 'settings', 'profile']);

test('the reminder setting is saved', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(SettingsPage::class)->set('reminderDaysBefore', 5)->call('saveReminder')->assertHasNoErrors();
    Livewire::test(SettingsPage::class)->set('reminderDaysBefore', 31)->call('saveReminder')->assertHasErrors(['reminderDaysBefore']);

    expect($user->refresh()->reminder_days_before)->toBe(5);
});

test('a PIN can be set, changed and removed', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(SettingsPage::class)
        ->set('newPin', '12')->set('newPinConfirmation', '12')->call('savePin')
        ->assertHasErrors(['newPin' => 'Mã PIN gồm 4 đến 6 chữ số.'])
        ->set('newPin', '1234')->set('newPinConfirmation', '1234')->call('savePin')
        ->assertHasNoErrors();
    expect(Hash::check('1234', $user->refresh()->pin_hash))->toBeTrue();

    Livewire::test(SettingsPage::class)
        ->set('currentPin', '0000')->set('newPin', '5678')->set('newPinConfirmation', '5678')->call('savePin')
        ->assertHasErrors(['currentPin' => 'Mã PIN hiện tại không đúng.'])
        ->set('currentPin', '1234')->call('removePin');
    expect($user->refresh()->pin_hash)->toBeNull();
});

test('pages lock after being idle when a PIN is set', function () {
    $user = User::factory()->create(['pin_hash' => '1234']);

    $this->actingAs($user)
        ->withSession(['pin.last_activity' => now()->subMinutes(6)->getTimestamp()])
        ->get(route('groups.index'))
        ->assertRedirect(route('unlock'));

    $this->withSession(['pin.last_activity' => now()->subMinutes(2)->getTimestamp()])
        ->get(route('groups.index'))
        ->assertOk();
});

test('pages are not locked without a PIN', function () {
    $this->actingAs(User::factory()->create())->get(route('groups.index'))->assertOk();
});

test('the correct PIN unlocks and returns to the requested page', function () {
    $this->actingAs(User::factory()->create(['pin_hash' => '1234']));
    session()->put('url.intended', route('reports'));

    Livewire::test(UnlockPage::class)->set('pin', '1234')->call('unlock')->assertRedirect(route('reports'));

    $this->get(route('reports'))->assertOk();
});

test('too many wrong PINs log the user out', function () {
    $this->actingAs(User::factory()->create(['pin_hash' => '1234']));
    $component = Livewire::test(UnlockPage::class);

    foreach (range(1, 4) as $attempt) {
        $component->set('pin', '0000')->call('unlock')->assertHasErrors(['pin' => 'Mã PIN không đúng.']);
    }
    $component->set('pin', '0000')->call('unlock')->assertRedirect(route('login'));

    $this->assertGuest();
});
