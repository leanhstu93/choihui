<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Actions\Groups\AddSlot;
use App\Actions\Groups\DeleteGroup;
use App\Actions\Groups\FinishGroup;
use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Groups\RemoveSlot;
use App\Actions\Groups\UndoGroupBroken;
use App\Domain\HuiSchedule;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreMySlotRequest;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Queries\ProfitReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chi tiết dây: thông tin, tay của mình, lịch các kỳ có màu theo trạng thái, tổng tiền.
 */
#[Layout('layouts.app')]
class GroupShow extends Component
{
    #[Locked]
    public HuiGroup $group;

    public string $newSlotLabel = '';

    public ?int $brokenRoundNo = null;

    public ?string $actionError = null;

    public function mount(HuiGroup $group): void
    {
        Gate::authorize('view', $group);
        $this->group = $group;
    }

    public function addSlot(AddSlot $addSlot): void
    {
        Gate::authorize('update', $this->group);

        $validated = StoreMySlotRequest::makeValidator([
            'group_id' => $this->group->id,
            'label' => trim($this->newSlotLabel),
        ], Auth::user())->validate();

        $this->runAction(fn () => $addSlot->handle($this->group, $validated['label']));
        $this->newSlotLabel = '';
    }

    public function removeSlot(string $slotId, RemoveSlot $removeSlot): void
    {
        $slot = MySlot::query()->where('group_id', $this->group->id)->findOrFail($slotId);
        Gate::authorize('delete', $slot);

        $this->runAction(fn () => $removeSlot->handle($slot));
    }

    public function markBroken(MarkGroupBroken $markGroupBroken): void
    {
        Gate::authorize('update', $this->group);

        if ($this->brokenRoundNo === null) {
            $this->addError('broken_round_no', 'Vui lòng nhập kỳ bể.');

            return;
        }

        $this->runAction(fn () => $markGroupBroken->handle($this->group, $this->brokenRoundNo));
    }

    public function undoBroken(UndoGroupBroken $undoGroupBroken): void
    {
        Gate::authorize('update', $this->group);
        $this->runAction(fn () => $undoGroupBroken->handle($this->group));
    }

    public function finish(FinishGroup $finishGroup): void
    {
        Gate::authorize('update', $this->group);
        $this->runAction(fn () => $finishGroup->handle($this->group));
    }

    public function delete(DeleteGroup $deleteGroup): void
    {
        Gate::authorize('delete', $this->group);
        $deleteGroup->handle($this->group);

        session()->flash('status', 'Đã xóa dây hụi.');
        $this->redirectRoute('groups.index', navigate: true);
    }

    public function render(ProfitReport $profitReport): View
    {
        $this->group->load(['slots.wonRound', 'rounds.cashEntries']);
        $summary = $profitReport->forGroup($this->group);

        return view('livewire.groups.group-show', [
            'roundStates' => $this->group->rounds->mapWithKeys(fn (HuiRound $round) => [$round->id => $this->roundState($round)]),
            'totalPaid' => $summary['paid'],
            'totalReceived' => $summary['received'],
            'profit' => $summary['profit'],
            'ifWinNext' => $summary['ifWinNext'],
        ])->title($this->group->name);
    }

    /**
     * Trạng thái hiển thị của một kỳ, nhìn từ phía người chơi.
     *
     * @return array{key: string, label: string}
     */
    private function roundState(HuiRound $round): array
    {
        $group = $this->group;
        $today = now()->toDateString();

        if ($group->status === HuiGroupStatus::Be && $round->round_no >= $group->broken_round_no) {
            return ['key' => 'be', 'label' => 'Bể'];
        }

        if ($round->bid_amount === null) {
            return $round->open_date->toDateString() <= $today
                ? ['key' => 'cho_nhap', 'label' => 'Chờ nhập kết quả']
                : ['key' => 'chua_mo', 'label' => 'Chưa mở'];
        }

        if ($round->winner_type === WinnerType::Minh) {
            return ['key' => 'minh_hot', 'label' => 'Mình hốt'];
        }

        $payments = $round->cashEntries->where('direction', CashDirection::Dong);

        if ($payments->contains(fn (CashEntry $entry) => $entry->status === CashEntryStatus::Thieu)) {
            return ['key' => 'thieu', 'label' => 'Đóng thiếu'];
        }

        if ($payments->contains(fn (CashEntry $entry) => $entry->status === CashEntryStatus::Chua)) {
            $dueDate = HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $group->due_offset_days)->format('Y-m-d');

            return $dueDate < $today
                ? ['key' => 'qua_han', 'label' => 'Quá hạn']
                : ['key' => 'can_dong', 'label' => 'Cần đóng'];
        }

        return ['key' => 'da_dong', 'label' => 'Đã đóng'];
    }

    /**
     * Chạy một Action, đổi lỗi nghiệp vụ thành thông báo trên màn hình.
     */
    private function runAction(callable $action): void
    {
        $this->actionError = null;

        try {
            $action();
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }
}
