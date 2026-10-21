<?php

declare(strict_types=1);

use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Payments\MarkPaid;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\WinnerType;
use App\Models\User;
use App\Queries\UpcomingPayment;
use App\Queries\UpcomingPayments;
use Illuminate\Support\Facades\Auth;

/**
 * @return list<string> "kỳ:tay:số tiền[:ước tính]"
 */
function describePayments(array $payments): array
{
    return array_map(
        fn (UpcomingPayment $payment) => $payment->roundNo.':'.$payment->slotLabel.':'.$payment->amount.($payment->isEstimate ? ':uoc-tinh' : ''),
        $payments,
    );
}

test('upcoming payments list unpaid entries and estimate rounds without a bid', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup(['start_date' => '2026-01-05', 'total_slots' => 4], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
    app(RecordRoundResult::class)->handle($group->rounds[1], 200_000, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($group->rounds[1]->cashEntries()->whereBelongsTo($group->slots[0], 'slot')->sole(), 1_000_000, new DateTimeImmutable('2026-02-05'));

    $payments = app(UpcomingPayments::class)->forUser($user, new DateTimeImmutable('2026-03-31'));

    // Kỳ 3 lấy lãi kỳ gần nhất (kỳ 2) 200.000; Tay 1 đã hốt kỳ 1 nên đóng đủ M.
    expect(describePayments($payments))->toBe([
        '1:Tay 2:1000000',
        '2:Tay 2:800000',
        '3:Tay 1:1000000:uoc-tinh',
        '3:Tay 2:800000:uoc-tinh',
    ]);
});

test('upcoming payments skip broken and finished groups', function () {
    $this->actingAs($user = User::factory()->create());
    app(MarkGroupBroken::class)->handle(createHuiGroup(['start_date' => '2026-01-05']), 1);

    expect(app(UpcomingPayments::class)->forUser($user, new DateTimeImmutable('2026-12-31')))->toBe([]);
});

test('upcoming payments only contain the given user\'s data even when no one is signed in', function () {
    $alice = User::factory()->create();
    $this->actingAs($alice);
    createHuiGroup(['name' => 'Của Alice', 'start_date' => '2026-01-05']);
    $this->actingAs(User::factory()->create());
    createHuiGroup(['name' => 'Của Bob', 'start_date' => '2026-01-05']);
    Auth::logout();

    $payments = app(UpcomingPayments::class)->forUser($alice, new DateTimeImmutable('2026-01-31'));

    expect(array_unique(array_map(fn (UpcomingPayment $payment) => $payment->groupName, $payments)))->toBe(['Của Alice']);
});
