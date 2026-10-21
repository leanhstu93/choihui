<?php

declare(strict_types=1);

use App\Actions\Groups\DeleteGroup;
use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Groups\UndoGroupBroken;
use App\Actions\Host\AssignSlot;
use App\Actions\Host\CreateHostGroup;
use App\Actions\Host\MarkAllCollected;
use App\Actions\Host\MarkCollected;
use App\Actions\Host\MarkPaidOut;
use App\Actions\Host\RecordHostRoundResult;
use App\Actions\Members\DeleteMember;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\Member;
use App\Models\User;

/**
 * @return array<int, array{direction: string, expected: int}> số tay => khoản của kỳ
 */
function hostEntriesOfRound(HuiRound $round): array
{
    return $round->hostEntries()->with('hostSlot')->get()
        ->mapWithKeys(fn (HostEntry $entry) => [$entry->hostSlot->slot_no => ['direction' => $entry->direction->value, 'expected' => $entry->amount_expected]])
        ->sortKeys()
        ->all();
}

function hostEntryOf(HuiRound $round, int $slotNo): HostEntry
{
    return $round->hostEntries()->whereHas('hostSlot', fn ($query) => $query->where('slot_no', $slotNo))->sole();
}

/**
 * Dây 10 tay, thảo 200.000; tay 1 và 2 đã hốt kỳ 1 và 2 (document/04-chu-hui.md V1).
 */
function hostGroupAfterTwoRounds(): HuiGroup
{
    $group = createHostGroup(['commission_amount' => 200_000]);
    app(RecordHostRoundResult::class)->handle($group->rounds[0], 0, $group->hostSlots[0]);
    app(RecordHostRoundResult::class)->handle($group->rounds[1], 100_000, $group->hostSlots[1]);

    return $group;
}

test('creating a host group assigns every slot to a member and creates N rounds', function () {
    $this->actingAs(User::factory()->host()->create());

    $group = createHostGroup(['total_slots' => 3]);

    expect($group->hostSlots->map(fn (HostSlot $slot) => [$slot->slot_no, $slot->member->name])->all())
        ->toBe([[1, 'Hụi viên 1'], [2, 'Hụi viên 2'], [3, 'Hụi viên 3']])
        ->and($group->rounds)->toHaveCount(3)
        ->and($group->slots)->toHaveCount(0);
});

test('a host group needs one member per slot, all from the host\'s own list', function (Closure $memberIds, string $message) {
    $this->actingAs($host = User::factory()->host()->create());

    expect(fn () => app(CreateHostGroup::class)->handle(
        ['name' => 'X', 'face_value' => 1_000_000, 'total_slots' => 2, 'start_date' => '2026-01-05'],
        $memberIds($host),
    ))->toThrow(HuiActionException::class, $message);
})->with([
    'too few members' => [fn () => [Member::create(['name' => 'A'])->id], 'Cần gán đủ 2 tay cho hụi viên.'],
    'another host\'s member' => [fn () => [Member::create(['name' => 'A'])->id, Member::withoutEvents(fn () => Member::factory()->create())->id], 'Hụi viên không tồn tại.'],
]);

test('a round result creates a collection from every other slot and a payout for the winner (V1)', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();

    app(RecordHostRoundResult::class)->handle($group->rounds[2], 100_000, $group->hostSlots[4]);

    $entries = hostEntriesOfRound($group->rounds[2]);
    $collections = array_filter($entries, fn (array $entry) => $entry['direction'] === 'thu');

    expect($entries[5])->toBe(['direction' => 'chi', 'expected' => 8_100_000])
        ->and($entries[1])->toBe(['direction' => 'thu', 'expected' => 1_000_000])
        ->and($entries[3])->toBe(['direction' => 'thu', 'expected' => 900_000])
        ->and($collections)->toHaveCount(9)
        ->and(array_sum(array_column($collections, 'expected')))->toBe(8_300_000);
});

test('invalid round results are rejected', function (Closure $record, string $message) {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();

    expect(fn () => $record($group))->toThrow(HuiActionException::class, $message);
})->with([
    'winner already won' => [fn (HuiGroup $group) => app(RecordHostRoundResult::class)->handle($group->rounds[2], 100_000, $group->hostSlots[1]), 'Tay 2 đã hốt ở kỳ 2.'],
    'winner without bid' => [fn (HuiGroup $group) => app(RecordHostRoundResult::class)->handle($group->rounds[2], null, $group->hostSlots[4]), 'Cần nhập lãi thăm trước khi chọn tay hốt.'],
    'winner from another group' => [fn (HuiGroup $group) => app(RecordHostRoundResult::class)->handle($group->rounds[2], 100_000, createHostGroup()->hostSlots[4]), 'Tay hốt không thuộc dây này.'],
]);

test('clearing a result removes its unpaid entries', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();

    app(RecordHostRoundResult::class)->handle($group->rounds[1], null, null);

    expect($group->rounds[1]->hostEntries()->count())->toBe(0)
        ->and(hostEntriesOfRound($group->rounds[0]))->toHaveCount(10);
});

test('changing a result that already has money asks for confirmation, then recalculates', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();
    app(MarkCollected::class)->handle(hostEntryOf($group->rounds[1], 3), 900_000, new DateTimeImmutable('2026-02-05'));

    expect(fn () => app(RecordHostRoundResult::class)->handle($group->rounds[1], 200_000, $group->hostSlots[1]))
        ->toThrow(ConfirmationRequiredException::class);
    expect(hostEntryOf($group->rounds[1], 3)->amount_expected)->toBe(900_000);

    // Livewire luôn nạp lại kỳ từ database sau khi bị hỏi xác nhận.
    app(RecordHostRoundResult::class)->handle($group->rounds[1]->fresh(), 200_000, $group->hostSlots[1], confirmed: true);

    $entry = hostEntryOf($group->rounds[1], 3);
    expect($entry->amount_expected)->toBe(800_000)
        ->and($entry->status)->toBe(CashEntryStatus::Du);
});

test('a collection gets its status from the amount and due date', function (int $amount, ?string $date, bool $writtenOff, CashEntryStatus $expected) {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();

    $entry = app(MarkCollected::class)->handle(hostEntryOf($group->rounds[1], 3), $amount, $date === null ? null : new DateTimeImmutable($date), writtenOff: $writtenOff);

    expect($entry->status)->toBe($expected);
})->with([
    'on time' => [900_000, '2026-02-05', false, CashEntryStatus::Du],
    'late' => [900_000, '2026-02-06', false, CashEntryStatus::Muon],
    'short' => [400_000, '2026-02-05', false, CashEntryStatus::Thieu],
    'written off' => [400_000, '2026-02-05', true, CashEntryStatus::Bung],
    'unmarked' => [0, null, false, CashEntryStatus::Chua],
]);

test('collect all marks only the unmarked collections of the round', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();
    app(MarkCollected::class)->handle(hostEntryOf($group->rounds[1], 3), 400_000, new DateTimeImmutable('2026-02-05'));

    $count = app(MarkAllCollected::class)->handle($group->rounds[1], new DateTimeImmutable('2026-02-05'));

    expect($count)->toBe(8)
        ->and(hostEntryOf($group->rounds[1], 3)->amount_actual)->toBe(400_000)
        ->and(hostEntryOf($group->rounds[1], 4)->status)->toBe(CashEntryStatus::Du)
        ->and(hostEntryOf($group->rounds[1], 2)->status)->toBe(CashEntryStatus::Chua);
});

test('the payout is recorded on the winner\'s entry', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();

    $entry = app(MarkPaidOut::class)->handle(hostEntryOf($group->rounds[1], 2), 8_100_000, new DateTimeImmutable('2026-02-05'));

    expect($entry->direction)->toBe(HostDirection::Chi)
        ->and($entry->status)->toBe(CashEntryStatus::Du);
});

test('collections and payouts cannot be swapped', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();
    $date = new DateTimeImmutable('2026-02-05');

    expect(fn () => app(MarkCollected::class)->handle(hostEntryOf($group->rounds[1], 2), 1, $date))
        ->toThrow(HuiActionException::class, 'Đây là khoản chi, hãy dùng ghi nhận tiền chi.')
        ->and(fn () => app(MarkPaidOut::class)->handle(hostEntryOf($group->rounds[1], 3), 1, $date))
        ->toThrow(HuiActionException::class, 'Đây là khoản thu, hãy dùng ghi nhận tiền thu.');
});

test('a slot that has not won can change holder, a slot that has won cannot', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();
    $newMember = Member::create(['name' => 'Chị Mai']);

    app(AssignSlot::class)->handle($group->hostSlots[4], $newMember);

    expect($group->hostSlots[4]->refresh()->member_id)->toBe($newMember->id)
        ->and(fn () => app(AssignSlot::class)->handle($group->hostSlots[1], $newMember))
        ->toThrow(HuiActionException::class, 'Tay này đã hốt nên không thể đổi người giữ.');
});

test('a member who has held a slot is kept, others can be deleted', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = createHostGroup(['total_slots' => 2]);
    $spare = Member::create(['name' => 'Chị Mai']);

    app(DeleteMember::class)->handle($spare);

    expect(Member::find($spare->id))->toBeNull()
        ->and(fn () => app(DeleteMember::class)->handle($group->hostSlots[0]->member))
        ->toThrow(HuiActionException::class, 'Hụi viên đã giữ tay trong dây nên không thể xóa.');
});

test('a broken host group drops unpaid entries from the break and keeps collected money', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();
    app(MarkCollected::class)->handle(hostEntryOf($group->rounds[1], 3), 900_000, new DateTimeImmutable('2026-02-05'));

    app(MarkGroupBroken::class)->handle($group, 2);

    expect($group->rounds[1]->hostEntries()->pluck('amount_actual')->all())->toBe([900_000])
        ->and(hostEntriesOfRound($group->rounds[0]))->toHaveCount(10);

    app(UndoGroupBroken::class)->handle($group);

    expect(hostEntriesOfRound($group->rounds[1]))->toHaveCount(10);
});

test('deleting a host group removes its slots, rounds and entries but keeps the members', function () {
    $this->actingAs(User::factory()->host()->create());
    $group = hostGroupAfterTwoRounds();

    app(DeleteGroup::class)->handle($group);

    expect(HuiGroup::count())->toBe(0)
        ->and(HostSlot::count())->toBe(0)
        ->and(HostEntry::count())->toBe(0)
        ->and(HuiRound::count())->toBe(0)
        ->and(Member::count())->toBe(10);
});
