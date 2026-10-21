<?php

declare(strict_types=1);

namespace App\Livewire\Host;

use App\Actions\Groups\DeleteGroup;
use App\Actions\Groups\FinishGroup;
use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Groups\UndoGroupBroken;
use App\Actions\Host\AssignSlot;
use App\Domain\HuiSchedule;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Enums\HuiGroupStatus;
use App\Exceptions\HuiActionException;
use App\Http\Requests\AssignHostSlotRequest;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\Member;
use App\Queries\HostReport;
use App\Queries\MemberLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chi tiết dây chủ hụi: N tay và người giữ, lịch kỳ có màu theo tiến độ thu / chi,
 * tổng thu / chi / nợ (document/04-chu-hui.md mục 7).
 */
#[Layout('layouts.app')]
class GroupShow extends Component
{
    #[Locked]
    public HuiGroup $group;

    #[Locked]
    public ?string $editingSlotId = null;

    public string $slotMemberId = '';

    public ?int $brokenRoundNo = null;

    public ?string $actionError = null;

    public function mount(HuiGroup $group): void
    {
        Gate::authorize('view', $group);
        $this->group = $group;
    }

    public function editSlot(string $slotId): void
    {
        $slot = $this->findSlot($slotId);
        $this->resetValidation();
        $this->editingSlotId = $slot->id;
        $this->slotMemberId = $slot->member_id;
    }

    public function cancelSlot(): void
    {
        $this->reset(['editingSlotId', 'slotMemberId']);
    }

    public function saveSlot(AssignSlot $assignSlot): void
    {
        $slot = $this->findSlot((string) $this->editingSlotId);
        $response = Gate::inspect('update', $slot);

        if ($response->denied()) {
            $this->actionError = $response->message();

            return;
        }

        $validated = AssignHostSlotRequest::makeValidator(['member_id' => $this->slotMemberId], Auth::user())->validate();

        $this->runAction(fn () => $assignSlot->handle($slot, Member::query()->findOrFail($validated['member_id'])));
        $this->cancelSlot();
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
        $this->redirectRoute('host.groups.index', navigate: true);
    }

    public function render(HostReport $hostReport, MemberLedger $memberLedger): View
    {
        $this->group->load(['hostSlots.member', 'hostSlots.wonRound', 'hostSlots.hostEntries', 'rounds.hostEntries']);

        return view('livewire.host.group-show', [
            'summary' => $hostReport->forGroup($this->group),
            'slotDebts' => $this->group->hostSlots->mapWithKeys(fn (HostSlot $slot) => [$slot->id => $memberLedger->debtOf($slot->hostEntries)]),
            'roundStates' => $this->group->rounds->mapWithKeys(fn (HuiRound $round) => [$round->id => $this->roundState($round)]),
            'members' => $this->editingSlotId === null ? collect() : Member::query()->orderBy('name')->get(['id', 'name']),
        ])->title($this->group->name);
    }

    /**
     * Trạng thái hiển thị của một kỳ, nhìn từ phía chủ hụi.
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

        if ($round->winner_host_slot_id === null) {
            return $round->open_date->toDateString() <= $today
                ? ['key' => 'cho_nhap', 'label' => 'Chờ nhập kết quả']
                : ['key' => 'chua_mo', 'label' => 'Chưa mở'];
        }

        $collections = $round->hostEntries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu);
        $unpaidCount = $collections->filter(fn (HostEntry $entry): bool => in_array($entry->status, [CashEntryStatus::Chua, CashEntryStatus::Thieu], true))->count();

        if ($unpaidCount > 0) {
            $dueDate = HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $group->due_offset_days)->format('Y-m-d');

            return [
                'key' => $dueDate < $today ? 'qua_han' : 'dang_thu',
                'label' => 'Còn '.$unpaidCount.' tay chưa đóng',
            ];
        }

        $payout = $round->hostEntries->first(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi);

        if ($payout !== null && $payout->status === CashEntryStatus::Chua) {
            return ['key' => 'chua_chi', 'label' => 'Chưa chi'];
        }

        return ['key' => 'xong', 'label' => 'Xong'];
    }

    private function findSlot(string $slotId): HostSlot
    {
        return HostSlot::query()->where('group_id', $this->group->id)->findOrFail($slotId);
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
