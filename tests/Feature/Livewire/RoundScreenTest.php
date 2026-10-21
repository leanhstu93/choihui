<?php

declare(strict_types=1);

use App\Enums\CashEntryStatus;
use App\Enums\WinnerType;
use App\Livewire\Rounds\RoundShow;
use App\Models\CashEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('another user\'s round page returns 404', function () {
    $this->actingAs(User::factory()->create());
    $round = createHuiGroup()->rounds[0];

    $this->actingAs(User::factory()->create())->get(route('rounds.show', $round))->assertNotFound();
});

test('recording a result where my slot wins creates the payout', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['commission_amount' => 1_000_000]);
    $round = $group->rounds[2];

    Livewire::test(RoundShow::class, ['round' => $round])
        ->set('bidAmount', 100_000)
        ->set('winnerType', 'minh')
        ->assertSeeHtml('Dự kiến nhận: <strong>7.300.000 đ</strong>')
        ->set('winnerSlotId', $group->slots[0]->id)
        ->call('saveResult')
        ->assertHasNoErrors()
        ->assertRedirect(route('rounds.show', $round));

    expect($round->cashEntries()->sole()->amount_expected)->toBe(7_300_000);
});

test('a slot of another user cannot be chosen as the winner', function () {
    $this->actingAs(User::factory()->create());
    $bobSlot = createHuiGroup()->slots[0];

    $this->actingAs(User::factory()->create());
    $round = createHuiGroup()->rounds[0];

    Livewire::test(RoundShow::class, ['round' => $round])
        ->set('bidAmount', 0)
        ->set('winnerType', 'minh')
        ->set('winnerSlotId', $bobSlot->id)
        ->call('saveResult')
        ->assertHasErrors(['winner_slot_id' => 'Tay hốt không thuộc dây này.']);

    expect($round->refresh()->winner_type)->toBe(WinnerType::ChuaCo);
});

test('choosing a winner requires a bid', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(RoundShow::class, ['round' => createHuiGroup()->rounds[0]])
        ->set('winnerType', 'nguoi_khac')
        ->call('saveResult')
        ->assertHasErrors(['bid_amount' => 'Cần nhập lãi thăm trước khi chọn người hốt.']);
});

test('a payment can be settled in one tap', function () {
    $this->actingAs(User::factory()->create());
    $round = createHuiGroup(['start_date' => now()->toDateString()])->rounds[0];
    $component = Livewire::test(RoundShow::class, ['round' => $round])
        ->set('bidAmount', 100_000)
        ->set('winnerType', 'nguoi_khac')
        ->call('saveResult');
    $entry = $round->cashEntries()->sole();

    Livewire::test(RoundShow::class, ['round' => $round])->call('settleInFull', $entry->id);

    expect($entry->refresh()->amount_actual)->toBe(900_000)
        ->and($entry->status)->toBe(CashEntryStatus::Du)
        ->and($entry->paid_date->toDateString())->toBe(now()->toDateString());
});

test('a partial payment can be entered with a date and note', function () {
    $this->actingAs(User::factory()->create());
    $round = createHuiGroup(['start_date' => '2026-01-05'])->rounds[0];
    Livewire::test(RoundShow::class, ['round' => $round])->set('bidAmount', 100_000)->set('winnerType', 'nguoi_khac')->call('saveResult');
    $entry = $round->cashEntries()->sole();

    Livewire::test(RoundShow::class, ['round' => $round])
        ->call('editEntry', $entry->id)
        ->assertSet('payAmount', 900_000)
        ->set('payAmount', 500_000)
        ->set('payDate', '2026-01-05')
        ->set('payNote', 'Đóng trước một nửa')
        ->call('saveEntry')
        ->assertHasNoErrors()
        ->assertSee('Đóng trước một nửa');

    expect($entry->refresh()->status)->toBe(CashEntryStatus::Thieu);
});

test('a payment needs a date', function () {
    $this->actingAs(User::factory()->create());
    $round = createHuiGroup()->rounds[0];
    Livewire::test(RoundShow::class, ['round' => $round])->set('bidAmount', 100_000)->set('winnerType', 'nguoi_khac')->call('saveResult');

    Livewire::test(RoundShow::class, ['round' => $round])
        ->call('editEntry', $round->cashEntries()->sole()->id)
        ->set('payDate', '')
        ->call('saveEntry')
        ->assertHasErrors(['paid_date']);
});

test('an entry of another round cannot be settled from this page', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    Livewire::test(RoundShow::class, ['round' => $group->rounds[1]])->set('bidAmount', 0)->set('winnerType', 'nguoi_khac')->call('saveResult');
    $otherEntry = CashEntry::sole();

    expect(fn () => Livewire::test(RoundShow::class, ['round' => $group->rounds[0]])->call('settleInFull', $otherEntry->id))
        ->toThrow(ModelNotFoundException::class);

    expect($otherEntry->refresh()->amount_actual)->toBe(0);
});

test('changing the bid of a paid round asks for confirmation', function () {
    $this->actingAs(User::factory()->create());
    $round = createHuiGroup()->rounds[0];
    Livewire::test(RoundShow::class, ['round' => $round])->set('bidAmount', 100_000)->set('winnerType', 'nguoi_khac')->call('saveResult');
    Livewire::test(RoundShow::class, ['round' => $round])->call('settleInFull', $round->cashEntries()->sole()->id);

    Livewire::test(RoundShow::class, ['round' => $round])
        ->set('bidAmount', 50_000)
        ->call('saveResult')
        ->assertSee('Thay đổi này ảnh hưởng 1 khoản đã ghi nhận tiền.')
        ->call('saveResult', true)
        ->assertRedirect(route('rounds.show', $round));

    expect($round->refresh()->bid_amount)->toBe(50_000);
});
