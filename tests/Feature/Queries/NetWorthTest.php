<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Payments\MarkPaid;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\CashEntryStatus;
use App\Enums\GoldType;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Livewire\NetWorth\NetWorthPage;
use App\Models\CashEntry;
use App\Models\GoldItem;
use App\Models\GoldPrice;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\Queries\NetWorthReport;
use App\Queries\UserBackup;
use Livewire\Livewire;
use Livewire\Volt\Volt;

/*
 * Tổng tài sản (document/07-tong-tai-san.md).
 */

test('the worked example of section 3 adds up', function () {
    $this->actingAs($user = User::factory()->create());

    $unwon = createHuiGroup(['name' => 'Dây A', 'face_value' => 20_000_000, 'total_slots' => 3]);
    app(RecordRoundResult::class)->handle($unwon->rounds[0], 0, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($unwon->rounds[0]->cashEntries()->sole(), 20_000_000, new DateTimeImmutable('2026-01-05'));

    $won = createHuiGroup(['name' => 'Dây B', 'face_value' => 2_000_000, 'total_slots' => 6]);
    app(RecordRoundResult::class)->handle($won->rounds[0], 0, WinnerType::Minh, $won->slots[0]);

    GoldItem::factory()->for($user)->create(['gold_type' => GoldType::Sjc, 'weight_ly' => 100]);
    GoldPrice::factory()->for($user)->create(['gold_type' => GoldType::Sjc, 'price_per_chi' => 50_000_000]);

    expect(app(NetWorthReport::class)->forUser($user))->toMatchArray([
        'gold' => 50_000_000,
        'huiPaid' => 20_000_000,
        'huiDebt' => 10_000_000,
        'assets' => 70_000_000,
        'debts' => 10_000_000,
        'net' => 60_000_000,
    ]);
});

test('the debt of a won slot counts unpaid rows and skips written-off ones', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup(['face_value' => 2_000_000, 'total_slots' => 5]);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
    app(RecordRoundResult::class)->handle($group->rounds[1], 100_000, WinnerType::NguoiKhac);
    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($group->rounds[1]->cashEntries()->sole(), 1_500_000, new DateTimeImmutable('2026-02-05'));

    // Kỳ 2 thiếu 500.000, kỳ 3 chưa đóng 2.000.000, kỳ 4 và 5 chưa mở 4.000.000.
    expect(app(NetWorthReport::class)->forUser($user)['huiDebt'])->toBe(6_500_000);

    CashEntry::query()->whereBelongsTo($group->rounds[2], 'round')->update(['status' => CashEntryStatus::Bung]);

    expect(app(NetWorthReport::class)->forUser($user)['huiDebt'])->toBe(4_500_000);
});

test('rounds before the win are not counted as debt when they have no row', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup(['face_value' => 1_000_000, 'total_slots' => 5]);
    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::Minh, $group->slots[0]);

    expect(app(NetWorthReport::class)->forUser($user)['huiDebt'])->toBe(2_000_000);
});

test('slots in the same group are valued separately', function () {
    $this->actingAs($user = User::factory()->create());
    $group = createHuiGroup(['face_value' => 1_000_000, 'total_slots' => 4], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
    $entry = $group->rounds[0]->cashEntries()->where('slot_id', $group->slots[1]->id)->sole();
    app(MarkPaid::class)->handle($entry, 1_000_000, new DateTimeImmutable('2026-01-05'));

    expect(app(NetWorthReport::class)->forUser($user))->toMatchArray([
        'huiPaid' => 1_000_000,
        'huiDebt' => 3_000_000,
        'net' => -2_000_000,
    ]);
});

test('broken and finished groups count as zero', function () {
    $this->actingAs($user = User::factory()->create());
    $broken = createHuiGroup();
    app(RecordRoundResult::class)->handle($broken->rounds[0], 0, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($broken->rounds[0]->cashEntries()->sole(), 1_000_000, new DateTimeImmutable('2026-01-05'));
    app(MarkGroupBroken::class)->handle($broken, 2);
    createHuiGroup()->forceFill(['status' => HuiGroupStatus::KetThuc])->save();

    expect(app(NetWorthReport::class)->forUser($user))->toMatchArray(['assets' => 0, 'debts' => 0, 'groups' => []]);
});

test('a host counts only gold, never the groups they run', function () {
    $this->actingAs($host = User::factory()->host()->create());
    createHostGroup(['total_slots' => 3]);
    GoldItem::factory()->for($host)->create(['weight_ly' => 100]);
    GoldPrice::factory()->for($host)->create(['price_per_chi' => 15_000_000]);

    expect(app(NetWorthReport::class)->forUser($host))->toMatchArray(['net' => 15_000_000, 'huiPaid' => 0, 'huiDebt' => 0]);

    $this->get(route('net-worth'))->assertOk()
        ->assertSee('Các dây bạn làm chủ không tính vào đây')
        ->assertDontSee('Hụi chưa hốt');
});

test('gold without a price is left out and flagged', function () {
    $this->actingAs($user = User::factory()->create());
    GoldItem::factory()->for($user)->create();

    expect(app(NetWorthReport::class)->forUser($user))->toMatchArray(['gold' => 0, 'goldUnpriced' => 1]);

    Livewire::test(NetWorthPage::class)->assertSee('Chưa tính 1 món vàng chưa có giá');
});

test('one account never sees or counts the data of another', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    GoldItem::factory()->for($bob)->create(['weight_ly' => 100]);
    GoldPrice::factory()->for($bob)->create();
    NetWorthSnapshot::factory()->for($bob)->create(['month' => '2026-01', 'assets' => 999_000_000]);

    $this->actingAs($bob);
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);

    $this->actingAs($alice);

    expect(app(NetWorthReport::class)->forUser($alice))->toMatchArray(['net' => 0, 'debts' => 0, 'groups' => []]);
    Livewire::test(NetWorthPage::class)->assertDontSee('999.000.000');
});

test('opening the page keeps one row per month with the latest figures', function () {
    $this->actingAs($user = User::factory()->create());
    GoldItem::factory()->for($user)->create(['weight_ly' => 100]);
    $price = GoldPrice::factory()->for($user)->create(['price_per_chi' => 10_000_000]);

    $this->travelTo('2026-10-31 23:00:00');
    Livewire::test(NetWorthPage::class);
    $this->travelTo('2026-11-01 08:00:00');
    Livewire::test(NetWorthPage::class);
    $price->update(['price_per_chi' => 12_000_000]);
    Livewire::test(NetWorthPage::class);

    expect(NetWorthSnapshot::query()->orderBy('month')->pluck('assets', 'month')->all())
        ->toBe(['2026-10' => 10_000_000, '2026-11' => 12_000_000]);
});

test('the chart shows the latest twelve months including negative ones', function () {
    $this->actingAs($user = User::factory()->create());
    foreach (range(1, 12) as $month) {
        NetWorthSnapshot::factory()->for($user)->create(['month' => sprintf('2025-%02d', $month)]);
    }
    NetWorthSnapshot::factory()->for($user)->create(['month' => '2026-01', 'assets' => 0, 'debts' => 5_000_000]);

    $this->travelTo('2026-02-10');

    Livewire::test(NetWorthPage::class)
        ->assertViewHas('snapshots', fn ($snapshots) => $snapshots->first()->month === '2025-03' && $snapshots->last()->month === '2026-02')
        ->assertSee('01/2026: -5.000.000 đ');
});

test('login and the home page open the overview, which keeps its area in settings', function () {
    $user = User::factory()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertRedirect(route('net-worth', absolute: false));

    $this->actingAs($user);
    $this->get(route('net-worth'))->assertOk()->assertSeeInOrder(['Tổng', 'Hụi', 'Vàng', 'Tổng tài sản', 'Cài đặt']);
    $this->get(route('settings'))->assertOk()->assertDontSee('Dây hụi')->assertDontSee(route('gold.prices'));
});

test('the history survives a backup and an old file leaves it alone', function () {
    $alice = User::factory()->create();
    NetWorthSnapshot::factory()->for($alice)->create(['month' => '2026-03', 'assets' => 7, 'debts' => 2]);
    $backup = json_decode(json_encode(app(UserBackup::class)->export($alice)), true);

    $bob = User::factory()->create();
    NetWorthSnapshot::factory()->for($bob)->create(['month' => '2020-01']);
    $this->actingAs($bob);

    app(RestoreBackup::class)->handle($bob, ['app' => 'so-hui', 'version' => 1, 'groups' => []]);
    expect(NetWorthSnapshot::sole()->month)->toBe('2020-01');

    app(RestoreBackup::class)->handle($bob, $backup);
    expect(NetWorthSnapshot::sole()->only(['month', 'assets', 'debts']))->toBe(['month' => '2026-03', 'assets' => 7, 'debts' => 2]);
});

test('the home page counts down to Tết', function () {
    $this->actingAs(User::factory()->create());

    $this->travelTo('2026-11-13 08:00:00');
    Livewire::test(NetWorthPage::class)
        ->assertSee('Còn 85 ngày nữa là Tết')
        ->assertSee('Tết Đinh Mùi')
        ->assertSee('Mùng 1: Thứ Bảy, 06/02/2027');

    $this->travelTo('2027-02-06 08:00:00');
    Livewire::test(NetWorthPage::class)
        ->assertSee('Chúc mừng năm mới!')
        ->assertSee('Hôm nay mùng 1 Tết Đinh Mùi');
});
