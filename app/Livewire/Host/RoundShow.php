<?php

declare(strict_types=1);

namespace App\Livewire\Host;

use App\Actions\Host\MarkAllCollected;
use App\Actions\Host\MarkCollected;
use App\Actions\Host\MarkPaidOut;
use App\Actions\Host\RecordHostRoundResult;
use App\Domain\HostLedger;
use App\Domain\HuiSchedule;
use App\Enums\HostDirection;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Http\Requests\UpdateHostEntryRequest;
use App\Http\Requests\UpdateHostRoundRequest;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiRound;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chi tiết một kỳ của dây chủ hụi: nhập lãi thăm và tay hốt (CH-04), thu từng tay
 * 1 chạm hoặc "Thu đủ tất cả" (CH-06), chi cho tay hốt (CH-07).
 */
#[Layout('layouts.app')]
class RoundShow extends Component
{
    #[Locked]
    public HuiRound $round;

    public ?int $bidAmount = null;

    public string $winnerSlotId = '';

    public ?string $confirmationMessage = null;

    public ?string $actionError = null;

    #[Locked]
    public ?string $editingEntryId = null;

    public ?int $payAmount = null;

    public string $payDate = '';

    public string $payNote = '';

    public bool $writtenOff = false;

    public function mount(HuiRound $round): void
    {
        Gate::authorize('view', $round);
        $this->round = $round;
        $this->bidAmount = $round->bid_amount;
        $this->winnerSlotId = (string) $round->winner_host_slot_id;
    }

    public function saveResult(RecordHostRoundResult $recordHostRoundResult, bool $confirmed = false): void
    {
        Gate::authorize('update', $this->round);
        $this->actionError = null;

        $validated = UpdateHostRoundRequest::makeValidator([
            'bid_amount' => $this->bidAmount,
            'winner_host_slot_id' => $this->winnerSlotId === '' ? null : $this->winnerSlotId,
        ], $this->round, Auth::user())->validate();

        $winner = $validated['winner_host_slot_id'] === null ? null : HostSlot::query()->findOrFail($validated['winner_host_slot_id']);

        try {
            $recordHostRoundResult->handle($this->round, $validated['bid_amount'], $winner, $confirmed);
        } catch (ConfirmationRequiredException $exception) {
            $this->confirmationMessage = $exception->getMessage();

            return;
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->confirmationMessage = null;
        session()->flash('status', 'Đã lưu kết quả kỳ '.$this->round->round_no.'.');
        $this->redirectRoute('host.rounds.show', $this->round, navigate: true);
    }

    /**
     * Đánh dấu đã thu / đã chi đủ số tiền dự kiến, hôm nay (thao tác 1 chạm).
     */
    public function settleInFull(string $entryId, MarkCollected $markCollected, MarkPaidOut $markPaidOut): void
    {
        $entry = $this->findEntry($entryId);
        $this->record($entry, $entry->amount_expected, now()->toDateString(), null, false, $markCollected, $markPaidOut);
    }

    public function collectAll(MarkAllCollected $markAllCollected): void
    {
        Gate::authorize('update', $this->round);
        $count = $markAllCollected->handle($this->round, new DateTimeImmutable('today'));
        session()->flash('status', "Đã ghi thu đủ {$count} tay.");
    }

    public function editEntry(string $entryId): void
    {
        $entry = $this->findEntry($entryId);
        $this->resetValidation();
        $this->editingEntryId = $entry->id;
        $this->payAmount = $entry->amount_actual > 0 ? $entry->amount_actual : $entry->amount_expected;
        $this->payDate = $entry->paid_date?->toDateString() ?? now()->toDateString();
        $this->payNote = (string) $entry->note;
        $this->writtenOff = $entry->status->value === 'bung';
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingEntryId', 'payAmount', 'payDate', 'payNote', 'writtenOff']);
    }

    public function saveEntry(MarkCollected $markCollected, MarkPaidOut $markPaidOut): void
    {
        $entry = $this->findEntry((string) $this->editingEntryId);

        $validated = UpdateHostEntryRequest::makeValidator([
            'amount_actual' => $this->payAmount,
            'paid_date' => $this->payDate === '' ? null : $this->payDate,
            'written_off' => $this->writtenOff,
            'note' => trim($this->payNote) ?: null,
        ])->validate();

        $this->record($entry, $validated['amount_actual'], $validated['paid_date'], $validated['note'], (bool) $validated['written_off'], $markCollected, $markPaidOut);
        $this->cancelEdit();
    }

    public function render(): View
    {
        $this->round->load(['group.hostSlots.member', 'group.hostSlots.wonRound', 'group.rounds', 'hostEntries.hostSlot.member']);
        $group = $this->round->group;
        $entries = $this->round->hostEntries->sortBy('hostSlot.slot_no');
        $collections = $entries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu);
        $payout = $entries->first(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi);
        $collected = (int) $collections->sum('amount_actual');
        $paidOut = (int) $payout?->amount_actual;

        return view('livewire.host.round-show', [
            'group' => $group,
            'dueDate' => HuiSchedule::dueDate($this->round->open_date->toDateTimeImmutable(), $group->due_offset_days),
            'preview' => $this->preview(),
            'bidSuggestions' => $this->round->previousBids(),
            'availableSlots' => $group->hostSlots->filter(fn (HostSlot $slot) => $slot->wonRound === null || $slot->wonRound->is($this->round)),
            'collections' => $collections,
            'payout' => $payout,
            'totals' => [
                'expected' => (int) $collections->sum('amount_expected'),
                'collected' => $collected,
                'uncollectedCount' => $collections->filter(fn (HostEntry $entry): bool => $entry->status->value === 'chua')->count(),
                'paidOut' => $paidOut,
                'topUp' => HostLedger::topUp($collected, $paidOut),
            ],
        ])->title('Kỳ '.$this->round->round_no.' · '.$group->name);
    }

    /**
     * Tổng thu, tiền chi và tiền thảo nếu lưu kết quả đang nhập.
     *
     * @return array{collection: int, payout: int, commission: int}|null
     */
    private function preview(): ?array
    {
        $group = $this->round->group;
        $winner = $group->hostSlots->firstWhere('id', $this->winnerSlotId);

        if ($this->bidAmount === null || $winner === null) {
            return null;
        }

        $wonRoundBySlot = $group->hostSlots
            ->mapWithKeys(fn (HostSlot $slot): array => [$slot->slot_no => $slot->wonRound?->is($this->round) ? null : $slot->wonRound?->round_no])
            ->all();

        try {
            $entries = HostLedger::roundEntries($this->round->round_no, $group->total_slots, $group->face_value, $this->bidAmount, $group->commission_amount, $wonRoundBySlot, $winner->slot_no);
        } catch (InvalidArgumentException) {
            return null;
        }

        return [
            'collection' => array_sum($entries['collections']),
            'payout' => $entries['payout'],
            'commission' => $group->commission_amount,
        ];
    }

    private function findEntry(string $entryId): HostEntry
    {
        $entry = HostEntry::query()->where('round_id', $this->round->id)->findOrFail($entryId);
        Gate::authorize('update', $entry);

        return $entry;
    }

    private function record(HostEntry $entry, int $amount, ?string $date, ?string $note, bool $writtenOff, MarkCollected $markCollected, MarkPaidOut $markPaidOut): void
    {
        $this->actionError = null;
        $date = $date === null ? null : new DateTimeImmutable($date);

        try {
            if ($entry->direction === HostDirection::Chi) {
                $markPaidOut->handle($entry, $amount, $date, $note);
            } else {
                $markCollected->handle($entry, $amount, $date, $note, $writtenOff);
            }
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }
}
