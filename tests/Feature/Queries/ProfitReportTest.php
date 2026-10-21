<?php

declare(strict_types=1);

use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\WinnerType;
use App\Models\User;
use App\Queries\ProfitReport;

test('the report shows actual and projected profit per group', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup(['total_slots' => 4, 'start_date' => '2026-01-05']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::NguoiKhac);
    app(RecordRoundResult::class)->handle($group->rounds[1], 200_000, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($group->rounds[0]->cashEntries()->sole(), 1_000_000, new DateTimeImmutable('2026-01-05'));
    app(MarkPaid::class)->handle($group->rounds[1]->cashEntries()->sole(), 800_000, new DateTimeImmutable('2026-02-05'));

    $report = app(ProfitReport::class)->forUser($user);

    // Giống ví dụ HuiCalculatorTest: tay chưa hốt giả định hốt kỳ 4, dự kiến +300.000.
    expect($report['groups'][0])->toMatchArray(['paid' => 1_800_000, 'received' => 0, 'profit' => -1_800_000, 'projected' => 300_000])
        ->and($report['totals'])->toBe(['paid' => 1_800_000, 'received' => 0, 'profit' => -1_800_000, 'projected' => 300_000]);
});

test('broken groups only report actual profit (#7)', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup();
    foreach (range(0, 4) as $index) {
        app(RecordRoundResult::class)->handle($group->rounds[$index], 100_000, WinnerType::NguoiKhac);
        app(MarkPaid::class)->handle($group->rounds[$index]->cashEntries()->sole(), 900_000, new DateTimeImmutable('2026-01-05'));
    }
    app(MarkGroupBroken::class)->handle($group, 6);

    $report = app(ProfitReport::class)->forUser($user);

    expect($report['groups'][0]['profit'])->toBe(-4_500_000)
        ->and($report['groups'][0]['projected'])->toBeNull()
        ->and($report['totals']['projected'])->toBe(-4_500_000);
});

test('received payouts count towards actual profit', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup(['total_slots' => 2]);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
    app(RecordReceipt::class)->handle($group->rounds[0]->cashEntries()->sole(), 1_000_000, new DateTimeImmutable('2026-01-05'));

    expect(app(ProfitReport::class)->forUser($user)['groups'][0])->toMatchArray(['received' => 1_000_000, 'profit' => 1_000_000]);
});
