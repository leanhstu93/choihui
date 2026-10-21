<?php

declare(strict_types=1);

use App\Actions\Host\MarkCollected;
use App\Actions\Host\MarkPaidOut;
use App\Actions\Host\RecordHostRoundResult;
use App\Livewire\Host\Dashboard;
use App\Models\HostEntry;
use App\Models\HuiGroup;
use App\Models\User;
use App\Queries\HostDashboard;
use App\Queries\HostReport;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/**
 * Dây 3 tay, M = 1.000.000, thảo 200.000, kỳ 1 mở cách đây một tháng, tay 1 hốt
 * không lãi: thu tay 2 đủ 1.000.000, tay 3 mới đóng 400.000; đã chi đủ 1.800.000
 * nên mình bù 400.000, tay 3 còn nợ 600.000.
 */
function hostGroupWithShortRound(): HuiGroup
{
    $group = createHostGroup(['total_slots' => 3, 'commission_amount' => 200_000, 'start_date' => now()->subMonth()->toDateString()]);
    $round = $group->rounds[0];
    app(RecordHostRoundResult::class)->handle($round, 0, $group->hostSlots[0]);
    $entryOf = fn (int $slotNo): HostEntry => $round->hostEntries()->whereHas('hostSlot', fn ($query) => $query->where('slot_no', $slotNo))->sole();
    $paidDate = $round->open_date->toDateTimeImmutable();

    app(MarkCollected::class)->handle($entryOf(2), 1_000_000, $paidDate);
    app(MarkCollected::class)->handle($entryOf(3), 400_000, $paidDate);
    app(MarkPaidOut::class)->handle($entryOf(1), 1_800_000, $paidDate);

    return $group;
}

test('the host report adds up collections, payouts, commission, top-ups and debt', function () {
    $this->actingAs($host = User::factory()->host()->create());
    hostGroupWithShortRound();

    $report = app(HostReport::class)->forUser($host);

    // Dự kiến = thực tế −400.000 + nợ 600.000 + thảo 2 kỳ chưa hốt × 200.000.
    expect($report['groups'][0])->toMatchArray([
        'collected' => 1_400_000,
        'paidOut' => 1_800_000,
        'commissionReceived' => 200_000,
        'commissionProjected' => 600_000,
        'topUp' => 400_000,
        'debt' => 600_000,
        'writtenOff' => 0,
        'profit' => -400_000,
        'projected' => 600_000,
    ])->and($report['totals']['projected'])->toBe(600_000);
});

test('written-off shortfalls leave the debt and the projection', function () {
    $this->actingAs($host = User::factory()->host()->create());
    $group = hostGroupWithShortRound();
    $entry = $group->rounds[0]->hostEntries()->where('amount_actual', 400_000)->sole();
    app(MarkCollected::class)->handle($entry, 400_000, now()->toDateTimeImmutable(), writtenOff: true);

    expect(app(HostReport::class)->forUser($host)['groups'][0])->toMatchArray(['debt' => 0, 'writtenOff' => 600_000, 'projected' => 0]);
});

test('the host home page lists unpaid slots per round and rounds waiting for a result', function () {
    $this->actingAs($host = User::factory()->host()->create());
    hostGroupWithShortRound();
    Auth::logout();

    // Không ai đăng nhập (như khi chạy nền) vẫn chỉ lấy dữ liệu của chủ hụi đã cho.
    $dashboard = app(HostDashboard::class)->forUser($host, new DateTimeImmutable('today'), new DateTimeImmutable('+30 days'));

    expect($dashboard['totals'])->toBe(['toCollect' => 600_000, 'toPayOut' => 0, 'overdueCount' => 1])
        ->and($dashboard['rounds'])->toHaveCount(1)
        ->and(array_map(fn (HostEntry $entry) => $entry->hostSlot->slot_no, $dashboard['rounds'][0]['unpaid']))->toBe([3])
        ->and($dashboard['pendingResults'][0]['round']->round_no)->toBe(2)
        ->and(app(HostDashboard::class)->forUser(User::factory()->host()->create(), new DateTimeImmutable('today'), new DateTimeImmutable('+30 days'))['rounds'])->toBe([]);
});

test('collections and payouts can be settled in one tap from the host home page', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 2]);
    app(RecordHostRoundResult::class)->handle($group->rounds[0], 0, $group->hostSlots[0]);
    [$payout, $collection] = [$group->rounds[0]->hostEntries()->where('direction', 'chi')->sole(), $group->rounds[0]->hostEntries()->where('direction', 'thu')->sole()];

    Livewire::test(Dashboard::class)
        ->assertSee('Hụi viên 2')
        ->call('settleInFull', $collection->id)
        ->call('settleInFull', $payout->id)
        ->assertDontSeeHtml('Đã thu</button>')
        ->assertDontSeeHtml('Đã chi</button>')
        ->assertSee('Cần nhập kết quả');

    expect($collection->refresh()->amount_actual)->toBe(1_000_000)
        ->and($payout->refresh()->amount_actual)->toBe(1_000_000);
});

test('the host report page renders the totals', function () {
    $this->actingAs(User::factory()->host()->create());
    hostGroupWithShortRound();

    $this->get(route('host.reports'))->assertOk()->assertSeeInOrder(['Thảo đã nhận', '200.000 đ', 'Lời / lỗ dự kiến', '600.000 đ']);
});
