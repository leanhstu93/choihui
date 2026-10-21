<?php

declare(strict_types=1);

use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Livewire\Groups\GroupForm;
use App\Livewire\Groups\GroupIndex;
use App\Livewire\Groups\GroupShow;
use App\Models\HuiGroup;
use App\Models\MySlot;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('the group pages render for their owner', function (string $routeName, string $heading) {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();

    $this->get(route($routeName, $group))->assertOk()->assertSee($heading);
})->with([
    'detail' => ['groups.show', 'Lịch các kỳ'],
    'edit' => ['groups.edit', 'Sửa dây hụi'],
]);

test('another user\'s group pages return 404', function (string $routeName) {
    $bobGroup = (function () {
        $this->actingAs(User::factory()->create());

        return createHuiGroup();
    })->call($this);

    $this->actingAs(User::factory()->create())
        ->get(route($routeName, $bobGroup))
        ->assertNotFound();
})->with(['groups.show', 'groups.edit']);

test('guests are sent to the login page', function () {
    $this->get(route('groups.index'))->assertRedirect(route('login'));
});

test('the list only shows groups with the selected status', function () {
    $this->actingAs(User::factory()->create());
    createHuiGroup(['name' => 'Dây đang chạy']);
    createHuiGroup(['name' => 'Dây đã bể'])->update(['status' => HuiGroupStatus::Be, 'broken_round_no' => 1]);

    Livewire::test(GroupIndex::class)
        ->assertSee('Dây đang chạy')
        ->assertDontSee('Dây đã bể')
        ->set('status', 'be')
        ->assertSee('Dây đã bể')
        ->assertDontSee('Dây đang chạy');
});

test('the list counts unpaid entries per group', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup([], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);

    Livewire::test(GroupIndex::class)->assertSee('2 khoản chưa đóng')->assertSee('Kỳ 1/10');
});

test('a group can be created with its slots', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(GroupForm::class)
        ->set('name', 'Hụi 2tr chị Mai')
        ->set('faceValue', 2_000_000)
        ->set('totalSlots', 12)
        ->set('startDate', '2026-11-05')
        ->set('hostName', 'Chị Mai')
        ->set('commissionAmount', 500_000)
        ->call('addSlotLabel')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('groups.show', HuiGroup::sole()));

    $group = HuiGroup::sole();
    expect($group->user_id)->toBe($user->id)
        ->and($group->commission_amount)->toBe(500_000)
        ->and($group->slots()->pluck('label')->all())->toBe(['Tay 1', 'Tay 2'])
        ->and($group->rounds()->count())->toBe(12);
});

test('the form suggests face values and commissions used before, then common ones', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['face_value' => 4_000_000, 'commission_amount' => 300_000]);

    $html = Livewire::test(GroupForm::class)->html();
    $suggested = fn (string $model): array => preg_match_all("/\\\$set\('{$model}', (\d+)\)/", $html, $matches) ? array_map('intval', $matches[1]) : [];

    expect($suggested('faceValue'))->toBe([4_000_000, 1_000_000])
        ->and($suggested('commissionAmount'))->toBe([300_000, 200_000]);

    Livewire::test(GroupForm::class, ['group' => $group])
        ->assertDontSeeHtml("\$set('faceValue'");
});

test('creating a group reports invalid input in Vietnamese', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GroupForm::class)
        ->set('startDate', '')
        ->set('totalSlots', 1)
        ->call('save')
        ->assertHasErrors(['name', 'face_value', 'start_date', 'host_name', 'total_slots'])
        ->assertSee('Vui lòng nhập tên.')
        ->assertSee('Số tay phải từ 2 trở lên.');

    expect(HuiGroup::count())->toBe(0);
});

test('editing a group with a received payout asks for confirmation before changing the commission', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
    app(RecordReceipt::class)->handle($group->rounds[0]->cashEntries()->sole(), 9_000_000, new DateTimeImmutable('2026-01-05'));

    $component = Livewire::test(GroupForm::class, ['group' => $group])
        ->set('commissionAmount', 1_000_000)
        ->call('save')
        ->assertSee('Thay đổi này ảnh hưởng 1 khoản đã ghi nhận tiền.')
        ->assertNoRedirect();

    expect($group->refresh()->commission_amount)->toBe(0);

    $component->call('save', true)->assertRedirect(route('groups.show', $group));

    expect($group->refresh()->commission_amount)->toBe(1_000_000);
});

test('the group page shows round states and totals', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['start_date' => now()->subMonths(2)->toDateString()]);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
    app(MarkPaid::class)->handle($group->rounds[0]->cashEntries()->sole(), 900_000, now()->toDateTimeImmutable());
    app(RecordRoundResult::class)->handle($group->rounds[1], 100_000, WinnerType::NguoiKhac);

    Livewire::test(GroupShow::class, ['group' => $group])
        ->assertSeeInOrder(['Kỳ 1', 'Đã đóng', 'Kỳ 2', 'Quá hạn', 'Kỳ 3', 'Chờ nhập kết quả', 'Kỳ 4', 'Chưa mở'])
        ->assertSee('900.000 đ')
        ->assertSee('Nếu hốt kỳ 3');
});

test('slots can be added and removed from the group page', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();

    Livewire::test(GroupShow::class, ['group' => $group])
        ->set('newSlotLabel', 'Tay 2')
        ->call('addSlot')
        ->assertHasNoErrors()
        ->set('newSlotLabel', 'Tay 2')
        ->call('addSlot')
        ->assertHasErrors(['label' => 'Tên tay đã có trong dây này.'])
        ->call('removeSlot', MySlot::where('label', 'Tay 2')->value('id'));

    expect($group->slots()->pluck('label')->all())->toBe(['Tay 1']);
});

test('the group can be marked broken, restored and deleted', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();

    $component = Livewire::test(GroupShow::class, ['group' => $group])
        ->set('brokenRoundNo', 4)
        ->call('markBroken');
    expect($group->refresh()->status)->toBe(HuiGroupStatus::Be);

    $component->call('undoBroken');
    expect($group->refresh()->status)->toBe(HuiGroupStatus::DangChay);

    $component->call('delete')->assertRedirect(route('groups.index'));
    expect(HuiGroup::count())->toBe(0);
});

test('finishing a group early shows the business error', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GroupShow::class, ['group' => createHuiGroup(['total_slots' => 3])])
        ->call('finish')
        ->assertSee('Còn 3 kỳ chưa nhập kết quả.');
});

test('a slot from another group cannot be removed through this page', function () {
    $this->actingAs(User::factory()->create());
    $otherSlot = createHuiGroup([], ['Tay 1', 'Tay 2'])->slots[1];

    expect(fn () => Livewire::test(GroupShow::class, ['group' => createHuiGroup()])->call('removeSlot', $otherSlot->id))
        ->toThrow(ModelNotFoundException::class);

    expect($otherSlot->fresh())->not->toBeNull();
});
