<?php

declare(strict_types=1);

use App\Actions\Host\RecordHostRoundResult;
use App\Enums\CashEntryStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard;
use App\Livewire\Host\GroupForm;
use App\Livewire\Host\GroupShow;
use App\Livewire\Host\RoundShow;
use App\Livewire\Members\MemberIndex;
use App\Livewire\Settings\SettingsPage;
use App\Models\HostEntry;
use App\Models\HuiGroup;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Livewire\Volt\Volt;

/*
 * Giao diện chủ hụi (document/04-chu-hui.md mục 7, CH-01..CH-09).
 */

test('a new account can register as a host', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'Cô Hoa')
        ->set('email', 'hoa@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('role', 'chu_hui')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::sole()->role)->toBe(UserRole::ChuHui);
});

test('hosts are sent from the home page to their own home page and see the host navigation', function () {
    $this->actingAs(User::factory()->host()->create());

    Livewire::test(Dashboard::class)->assertRedirect(route('host.dashboard'));
    $this->get(route('host.dashboard'))->assertOk()
        ->assertSeeInOrder(['Trang chủ', 'Dây hụi', 'Hụi viên', 'Báo cáo', 'Cài đặt'])
        ->assertSee(route('host.reports'));
});

test('host pages render for their owner', function (Closure $url) {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 3]);

    $this->get($url($group))->assertOk();
})->with([
    'home' => [fn () => route('host.dashboard')],
    'report' => [fn () => route('host.reports')],
    'group list' => [fn () => route('host.groups.index')],
    'create group' => [fn () => route('host.groups.create')],
    'group' => [fn (HuiGroup $group) => route('host.groups.show', $group)],
    'edit group' => [fn (HuiGroup $group) => route('host.groups.edit', $group)],
    'round' => [fn (HuiGroup $group) => route('host.rounds.show', $group->rounds[0])],
    'members' => [fn () => route('host.members.index')],
    'member' => [fn (HuiGroup $group) => route('host.members.show', $group->hostSlots[0]->member)],
]);

test('another host\'s pages return 404 and players cannot open host pages', function (string $routeName, Closure $parameter) {
    $this->actingAs(User::factory()->host()->create());
    $url = route($routeName, $parameter(createHostGroup(['total_slots' => 2])));

    $this->actingAs(User::factory()->host()->create())->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
})->with([
    'group' => ['host.groups.show', fn (HuiGroup $group) => $group],
    'round' => ['host.rounds.show', fn (HuiGroup $group) => $group->rounds[0]],
    'member' => ['host.members.show', fn (HuiGroup $group) => $group->hostSlots[0]->member],
]);

test('players get 404 on the host pages that do not need a record', function (string $routeName) {
    $this->actingAs(User::factory()->create())->get(route($routeName))->assertNotFound();
})->with(['host.dashboard', 'host.reports', 'host.groups.index', 'host.groups.create', 'host.members.index']);

test('a host group is created with a member on every slot', function () {
    $this->actingAs(User::factory()->host()->create());
    $lan = Member::create(['name' => 'Chị Lan']);

    Livewire::test(GroupForm::class)
        ->set('name', 'Hụi 2tr nhà mình')
        ->set('faceValue', 2_000_000)
        ->set('totalSlots', 3)
        ->set('commissionAmount', 300_000)
        ->set('memberIds.0', $lan->id)
        ->call('fillEmptySlots', $lan->id)
        ->set('newMemberName', 'Anh Tư')
        ->call('addMember')
        ->assertSet('memberIds', [$lan->id, $lan->id, $lan->id])
        ->set('memberIds.2', Member::where('name', 'Anh Tư')->sole()->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('host.groups.show', HuiGroup::sole()));

    expect(HuiGroup::sole()->hostSlots()->with('member')->get()->pluck('member.name')->all())
        ->toBe(['Chị Lan', 'Chị Lan', 'Anh Tư']);
});

test('the group form requires a member for every slot', function () {
    $this->actingAs(User::factory()->host()->create());

    Livewire::test(GroupForm::class)
        ->set('name', 'Hụi 2tr nhà mình')
        ->set('faceValue', 2_000_000)
        ->set('totalSlots', 2)
        ->call('save')
        ->assertHasErrors(['member_ids.0', 'member_ids.1']);

    expect(HuiGroup::count())->toBe(0);
});

test('the round page records a result, collects in one tap and pays out', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 3, 'commission_amount' => 200_000, 'start_date' => now()->toDateString()]);
    $round = $group->rounds[1];
    app(RecordHostRoundResult::class)->handle($group->rounds[0], 0, $group->hostSlots[0]);

    Livewire::test(RoundShow::class, ['round' => $round])
        ->set('bidAmount', 100_000)
        ->set('winnerSlotId', $group->hostSlots[1]->id)
        // Tay 1 chết đóng 1.000.000, tay 3 sống đóng 900.000; chi 1.000.000 + 900.000 − 200.000.
        ->assertSeeHtml('Phải thu 2 tay: <strong>1.900.000 đ</strong>')
        ->assertSeeHtml('Chi cho người hốt: <strong>1.700.000 đ</strong>')
        ->call('saveResult')
        ->assertHasNoErrors();

    $collection = $round->hostEntries()->where('direction', 'thu')->whereHas('hostSlot', fn ($query) => $query->where('slot_no', 3))->sole();
    $payout = $round->hostEntries()->where('direction', 'chi')->sole();

    Livewire::test(RoundShow::class, ['round' => $round->fresh()])
        ->call('settleInFull', $collection->id)
        ->call('settleInFull', $payout->id)
        ->assertSee('Mình bù');

    expect($collection->refresh()->status)->toBe(CashEntryStatus::Du)
        ->and($payout->refresh()->amount_actual)->toBe(1_700_000);

    Livewire::test(RoundShow::class, ['round' => $round->fresh()])->call('collectAll');

    expect($round->hostEntries()->where('direction', 'thu')->where('status', 'chua')->count())->toBe(0);
});

test('a short collection can be written off from the round page', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 3]);
    app(RecordHostRoundResult::class)->handle($group->rounds[0], 0, $group->hostSlots[0]);
    $entry = $group->rounds[0]->hostEntries()->where('direction', 'thu')->firstOrFail();

    Livewire::test(RoundShow::class, ['round' => $group->rounds[0]])
        ->call('editEntry', $entry->id)
        ->set('payAmount', 400_000)
        ->set('writtenOff', true)
        ->call('saveEntry')
        ->assertHasNoErrors();

    expect($entry->refresh()->status)->toBe(CashEntryStatus::Bung);
});

test('the round page rejects a slot of another group as the winner', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 2]);
    $otherSlot = createHostGroup(['total_slots' => 2])->hostSlots[0];

    Livewire::test(RoundShow::class, ['round' => $group->rounds[0]])
        ->set('bidAmount', 0)
        ->set('winnerSlotId', $otherSlot->id)
        ->call('saveResult')
        ->assertHasErrors(['winner_host_slot_id' => 'Tay hốt không thuộc dây này.']);
});

test('the group page shows round progress and lets the host change who holds a slot', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 3, 'start_date' => now()->subMonth()->toDateString()]);
    app(RecordHostRoundResult::class)->handle($group->rounds[0], 0, $group->hostSlots[0]);
    $mai = Member::create(['name' => 'Chị Mai']);

    Livewire::test(GroupShow::class, ['group' => $group])
        ->assertSeeInOrder(['Kỳ 1', 'Còn 2 tay chưa đóng', 'Kỳ 2', 'Chờ nhập kết quả', 'Kỳ 3', 'Chưa mở'])
        ->call('editSlot', $group->hostSlots[1]->id)
        ->set('slotMemberId', $mai->id)
        ->call('saveSlot')
        ->assertHasNoErrors()
        ->assertSee('Chị Mai');

    expect($group->hostSlots[1]->refresh()->member_id)->toBe($mai->id);
});

test('members can be added, edited, searched and only deleted when they never held a slot', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 2]);

    $component = Livewire::test(MemberIndex::class)
        ->call('create')
        ->set('name', 'Chị Lan')
        ->set('phone', '0901234567')
        ->call('save')
        ->assertHasNoErrors();
    $lan = Member::where('name', 'Chị Lan')->sole();

    $component->call('edit', $lan->id)->set('name', 'Chị Lan bán vải')->call('save')
        ->set('search', '0901')
        ->assertSee('Chị Lan bán vải')
        ->assertDontSee('Hụi viên 1')
        ->call('delete', $group->hostSlots[0]->member_id)
        ->assertSet('actionError', 'Hụi viên đã giữ tay trong dây nên không thể xóa.')
        ->call('delete', $lan->id);

    expect(Member::find($lan->id))->toBeNull()
        ->and(Member::count())->toBe(2);
});

test('the role can only be switched while the account has no group', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(SettingsPage::class)->call('switchRole')->assertHasNoErrors();
    expect($user->refresh()->isHost())->toBeTrue();

    createHostGroup(['total_slots' => 2]);

    Livewire::test(SettingsPage::class)
        ->call('switchRole')
        ->assertHasErrors(['role' => 'Chỉ đổi được vai trò khi tài khoản chưa có dây hụi nào.']);
    expect($user->refresh()->isHost())->toBeTrue();
});

test('hosts cannot download or restore the player backup', function () {
    $this->actingAs(User::factory()->host()->create());

    $this->get(route('settings.backup'))->assertNotFound();
    $this->get(route('settings'))->assertOk()->assertDontSee('Sao lưu dữ liệu');
});

test('a collection belongs to the round page it is settled from', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 2]);
    app(RecordHostRoundResult::class)->handle($group->rounds[0], 0, $group->hostSlots[0]);
    app(RecordHostRoundResult::class)->handle($group->rounds[1], 0, $group->hostSlots[1]);
    $entryOfRoundTwo = HostEntry::where('round_id', $group->rounds[1]->id)->firstOrFail();

    Livewire::test(RoundShow::class, ['round' => $group->rounds[0]])->call('settleInFull', $entryOfRoundTwo->id);
})->throws(ModelNotFoundException::class);
