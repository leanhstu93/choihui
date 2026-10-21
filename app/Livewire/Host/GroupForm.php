<?php

declare(strict_types=1);

namespace App\Livewire\Host;

use App\Actions\Groups\UpdateGroup;
use App\Actions\Host\CreateHostGroup;
use App\Actions\Members\SaveMember;
use App\Exceptions\ConfirmationRequiredException;
use App\Http\Requests\StoreHostGroupRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateHuiGroupRequest;
use App\Livewire\Concerns\SuggestsGroupAmounts;
use App\Models\HuiGroup;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Thêm dây chủ hụi kèm hụi viên giữ từng tay (CH-01, CH-03), hoặc sửa thông tin dây.
 * Mệnh giá, số tay, ngày bắt đầu và người giữ tay chỉ chọn khi tạo; đổi người giữ
 * tay làm ở màn chi tiết dây.
 */
#[Layout('layouts.app')]
class GroupForm extends Component
{
    use SuggestsGroupAmounts;

    #[Locked]
    public ?HuiGroup $group = null;

    public string $name = '';

    public ?int $faceValue = null;

    public ?int $totalSlots = null;

    public string $startDate = '';

    public int $dueOffsetDays = 0;

    public int $commissionAmount = 0;

    public string $note = '';

    /** @var list<string> Hụi viên giữ tay 1..N ('' = chưa chọn). */
    public array $memberIds = [];

    public string $newMemberName = '';

    public ?string $confirmationMessage = null;

    public function mount(?HuiGroup $group = null): void
    {
        if ($group?->exists) {
            Gate::authorize('update', $group);
            $this->group = $group;
            $this->fill([
                'name' => $group->name,
                'faceValue' => $group->face_value,
                'totalSlots' => $group->total_slots,
                'startDate' => $group->start_date->toDateString(),
                'dueOffsetDays' => $group->due_offset_days,
                'commissionAmount' => $group->commission_amount,
                'note' => (string) $group->note,
            ]);

            return;
        }

        $this->startDate = now()->toDateString();
    }

    /**
     * Số ô chọn hụi viên luôn bằng số tay.
     */
    public function updatedTotalSlots(): void
    {
        $count = max(0, min(200, (int) $this->totalSlots));
        $this->memberIds = array_slice(array_pad($this->memberIds, $count, ''), 0, $count);
    }

    /**
     * Thêm nhanh hụi viên mới vào danh bạ và gán vào tay trống đầu tiên.
     */
    public function addMember(SaveMember $saveMember): void
    {
        $this->newMemberName = trim($this->newMemberName);
        $this->validate(['newMemberName' => StoreMemberRequest::rulesFor()['name']], [], ['newMemberName' => 'tên hụi viên']);
        $member = $saveMember->handle(null, ['name' => $this->newMemberName]);
        $this->newMemberName = '';

        $emptyIndex = array_search('', $this->memberIds, true);

        if ($emptyIndex !== false) {
            $this->memberIds[$emptyIndex] = $member->id;
        }
    }

    /**
     * Gán một hụi viên cho mọi tay còn trống (người giữ nhiều tay).
     */
    public function fillEmptySlots(string $memberId): void
    {
        $this->memberIds = array_map(fn (string $id): string => $id === '' ? $memberId : $id, $this->memberIds);
    }

    public function save(CreateHostGroup $createHostGroup, UpdateGroup $updateGroup, bool $confirmed = false): void
    {
        if ($this->group !== null) {
            $this->update($updateGroup, $confirmed);

            return;
        }

        $validated = StoreHostGroupRequest::makeValidator([
            'name' => trim($this->name),
            'face_value' => $this->faceValue,
            'total_slots' => $this->totalSlots,
            'start_date' => $this->startDate,
            'due_offset_days' => $this->dueOffsetDays,
            'commission_amount' => $this->commissionAmount,
            'note' => trim($this->note) ?: null,
            'member_ids' => $this->memberIds,
        ], Auth::user())->validate();

        $memberIds = $validated['member_ids'];
        unset($validated['member_ids']);

        $group = $createHostGroup->handle($validated, $memberIds);

        session()->flash('status', 'Đã tạo dây hụi và lịch '.$group->total_slots.' kỳ.');
        $this->redirectRoute('host.groups.show', $group, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.host.group-form', [
            'members' => Member::query()->orderBy('name')->get(['id', 'name']),
            ...$this->groupAmountSuggestions(),
        ])->title($this->group === null ? 'Thêm dây hụi' : 'Sửa dây hụi');
    }

    private function update(UpdateGroup $updateGroup, bool $confirmed): void
    {
        /** @var HuiGroup $group */
        $group = $this->group;
        Gate::authorize('update', $group);

        $validated = UpdateHuiGroupRequest::makeValidator([
            'name' => trim($this->name),
            'due_offset_days' => $this->dueOffsetDays,
            'host_name' => $group->host_name,
            'commission_amount' => $this->commissionAmount,
            'note' => trim($this->note) ?: null,
        ], $group)->validate();

        try {
            $updateGroup->handle($group, $validated, $confirmed);
        } catch (ConfirmationRequiredException $exception) {
            $this->confirmationMessage = $exception->getMessage();

            return;
        }

        session()->flash('status', 'Đã lưu thông tin dây.');
        $this->redirectRoute('host.groups.show', $group, navigate: true);
    }
}
