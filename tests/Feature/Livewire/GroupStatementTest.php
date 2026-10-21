<?php

declare(strict_types=1);

use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\WinnerType;
use App\Livewire\Groups\GroupStatement;
use App\Models\User;
use Livewire\Livewire;

test('the statement lists every round with my entries and the totals', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['name' => 'Hụi 1tr cô Hoa', 'total_slots' => 3], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::NguoiKhac, winnerName: 'Chị Lan');
    app(RecordRoundResult::class)->handle($group->rounds[1], 100_000, WinnerType::Minh, $group->slots[0]);
    app(MarkPaid::class)->handle($group->rounds[0]->cashEntries()->whereBelongsTo($group->slots[0], 'slot')->sole(), 1_000_000, new DateTimeImmutable('2026-01-05'));
    app(RecordReceipt::class)->handle($group->rounds[1]->cashEntries()->whereBelongsTo($group->slots[0], 'slot')->sole(), 1_500_000, new DateTimeImmutable('2026-02-05'));

    Livewire::test(GroupStatement::class, ['group' => $group])
        ->assertSee('Hụi 1tr cô Hoa')
        ->assertSee('Tay 1 (hốt kỳ 2)')
        ->assertSeeInOrder(['Chị Lan', 'Tay 1: Đóng 1.000.000 đ', 'Đủ', 'Tay 2: Đóng 1.000.000 đ', 'Chưa'])
        ->assertSeeInOrder(['Tay 1: Nhận 1.900.000 đ', '(thực 1.500.000 đ)', 'Còn nợ'])
        // Tay 1 hốt kỳ 2: nhận 1.900.000, đóng kỳ 1 và đóng chết kỳ 3 → −100.000.
        // Tay 2 giả định hốt kỳ 3 (kỳ trống duy nhất): nhận 2.000.000, đóng 1.000.000 + 900.000 → +100.000.
        ->assertSeeInOrder(['Đã đóng', '1.000.000 đ', 'Đã nhận', '1.500.000 đ', 'Nếu hốt kỳ 3', '0 đ', 'Nếu hốt kỳ cuối', '0 đ'])
        ->assertSee('so-hui-hui-1tr-co-hoa-'.now()->format('Y-m-d').'.png');
});

test('another user\'s statement returns 404', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();

    $this->actingAs(User::factory()->create())->get(route('groups.statement', $group))->assertNotFound();
});

test('the statement page renders for the owner', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('groups.statement', createHuiGroup()))->assertOk()->assertSee('Lưu / chia sẻ ảnh');
});
