<?php

declare(strict_types=1);

namespace App\Livewire\Rounds;

use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Domain\HuiCalculator;
use App\Domain\HuiSchedule;
use App\Enums\CashDirection;
use App\Enums\WinnerType;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Http\Requests\UpdateCashEntryRequest;
use App\Http\Requests\UpdateHuiRoundRequest;
use App\Models\CashEntry;
use App\Models\HuiRound;
use App\Models\MySlot;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chi tiết một kỳ: nhập lãi thăm và người hốt (FR-04), xem khoản đóng / nhận (FR-05, FR-07),
 * đánh dấu đã đóng 1 chạm hoặc nhập số tiền thực tế (FR-06).
 */
#[Layout('layouts.app')]
class RoundShow extends Component
{
    #[Locked]
    public HuiRound $round;

    public ?int $bidAmount = null;

    public string $winnerType = 'chua_co';

    public ?string $winnerSlotId = null;

    public string $winnerName = '';

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
        $this->fill([
            'bidAmount' => $round->bid_amount,
            'winnerType' => $round->winner_type->value,
            'winnerSlotId' => $round->winner_slot_id,
            'winnerName' => (string) $round->winner_name,
        ]);
    }

    public function saveResult(RecordRoundResult $recordRoundResult, bool $confirmed = false): void
    {
        Gate::authorize('update', $this->round);
        $this->actionError = null;

        $winnerType = WinnerType::tryFrom($this->winnerType);
        $validated = UpdateHuiRoundRequest::makeValidator([
            'bid_amount' => $this->bidAmount,
            'winner_type' => $this->winnerType,
            'winner_slot_id' => $winnerType === WinnerType::Minh ? $this->winnerSlotId : null,
            'winner_name' => $winnerType === WinnerType::NguoiKhac ? (trim($this->winnerName) ?: null) : null,
        ], $this->round, Auth::user())->validate();

        $winnerSlot = $validated['winner_slot_id'] === null ? null : MySlot::query()->findOrFail($validated['winner_slot_id']);

        try {
            $recordRoundResult->handle(
                $this->round,
                $validated['bid_amount'],
                WinnerType::from($validated['winner_type']),
                $winnerSlot,
                $validated['winner_name'],
                $confirmed,
            );
        } catch (ConfirmationRequiredException $exception) {
            $this->confirmationMessage = $exception->getMessage();

            return;
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->confirmationMessage = null;
        session()->flash('status', 'Đã lưu kết quả kỳ '.$this->round->round_no.'.');
        $this->redirectRoute('rounds.show', $this->round, navigate: true);
    }

    /**
     * Đánh dấu đã đóng / đã nhận đủ số tiền dự kiến, hôm nay (thao tác 1 chạm).
     */
    public function settleInFull(string $entryId, MarkPaid $markPaid, RecordReceipt $recordReceipt): void
    {
        $entry = $this->findEntry($entryId);
        $this->record($entry, $entry->amount_expected, now()->toDateString(), null, false, $markPaid, $recordReceipt);
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

    public function saveEntry(MarkPaid $markPaid, RecordReceipt $recordReceipt): void
    {
        $entry = $this->findEntry((string) $this->editingEntryId);

        $validated = UpdateCashEntryRequest::makeValidator([
            'amount_actual' => $this->payAmount,
            'paid_date' => $this->payDate === '' ? null : $this->payDate,
            'written_off' => $this->writtenOff,
            'note' => trim($this->payNote) ?: null,
        ])->validate();

        $this->record($entry, $validated['amount_actual'], $validated['paid_date'], $validated['note'], (bool) $validated['written_off'], $markPaid, $recordReceipt);
        $this->cancelEdit();
    }

    public function render(): View
    {
        $this->round->load(['group.slots.wonRound', 'group.rounds', 'cashEntries.slot']);
        $group = $this->round->group;

        return view('livewire.rounds.round-show', [
            'group' => $group,
            'dueDate' => HuiSchedule::dueDate($this->round->open_date->toDateTimeImmutable(), $group->due_offset_days),
            'winPreview' => $this->winPreview(),
            'bidSuggestions' => $this->round->previousBids(),
            'availableSlots' => $group->slots->filter(fn (MySlot $slot) => $slot->wonRound === null || $slot->wonRound->is($this->round)),
        ])->title('Kỳ '.$this->round->round_no.' · '.$group->name);
    }

    /**
     * Nếu một tay của mình hốt kỳ này với lãi thăm đang nhập: tiền nhận (đã trừ
     * thảo) và lời/lỗ dự kiến của tay đó khi dây chạy hết (kỳ chưa mở lấy lãi
     * kỳ gần nhất).
     *
     * @return array{payout: int, profit: int}|null
     */
    private function winPreview(): ?array
    {
        if ($this->bidAmount === null) {
            return null;
        }

        $group = $this->round->group;
        $roundNo = $this->round->round_no;
        $bidsByRound = [$roundNo => $this->bidAmount] + $group->rounds->pluck('bid_amount', 'round_no')->all();

        try {
            return [
                'payout' => HuiCalculator::netPayout($roundNo, $group->total_slots, $group->face_value, $this->bidAmount, $group->commission_amount),
                'profit' => HuiCalculator::projectedProfit($group->total_slots, $group->face_value, $group->commission_amount, $bidsByRound, $roundNo),
            ];
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function findEntry(string $entryId): CashEntry
    {
        $entry = CashEntry::query()->where('round_id', $this->round->id)->findOrFail($entryId);
        Gate::authorize('update', $entry);

        return $entry;
    }

    private function record(CashEntry $entry, int $amount, ?string $date, ?string $note, bool $writtenOff, MarkPaid $markPaid, RecordReceipt $recordReceipt): void
    {
        $this->actionError = null;
        $date = $date === null ? null : new DateTimeImmutable($date);

        try {
            if ($entry->direction === CashDirection::Nhan) {
                $recordReceipt->handle($entry, $amount, $date, $writtenOff, $note);
            } else {
                $markPaid->handle($entry, $amount, $date, $note);
            }
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }
}
