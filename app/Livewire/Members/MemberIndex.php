<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Members\DeleteMember;
use App\Actions\Members\SaveMember;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreMemberRequest;
use App\Models\Member;
use App\Queries\MemberLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Danh bạ hụi viên của chủ hụi (CH-02): thêm, sửa, xóa, tìm theo tên / SĐT, xem nợ.
 */
#[Layout('layouts.app')]
#[Title('Hụi viên')]
class MemberIndex extends Component
{
    public string $search = '';

    #[Locked]
    public ?string $editingMemberId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $phone = '';

    public string $note = '';

    public ?string $actionError = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $memberId): void
    {
        $member = $this->findMember($memberId);
        $this->authorize('update', $member);
        $this->resetForm();
        $this->fill([
            'editingMemberId' => $member->id,
            'showForm' => true,
            'name' => $member->name,
            'phone' => (string) $member->phone,
            'note' => (string) $member->note,
        ]);
    }

    public function save(SaveMember $saveMember): void
    {
        $member = $this->editingMemberId === null ? null : $this->findMember($this->editingMemberId);

        if ($member !== null) {
            $this->authorize('update', $member);
        }

        $validated = StoreMemberRequest::makeValidator([
            'name' => trim($this->name),
            'phone' => trim($this->phone) ?: null,
            'note' => trim($this->note) ?: null,
        ])->validate();

        $saveMember->handle($member, $validated);
        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function delete(string $memberId, DeleteMember $deleteMember): void
    {
        $member = $this->findMember($memberId);
        $this->actionError = null;
        $response = Gate::inspect('delete', $member);

        if ($response->denied()) {
            $this->actionError = $response->message();

            return;
        }

        try {
            $deleteMember->handle($member);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }

    public function render(MemberLedger $memberLedger): View
    {
        $term = trim($this->search);
        $members = Member::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->with(['hostSlots.hostEntries'])
            ->orderBy('name')
            ->get();

        return view('livewire.members.member-index', [
            'members' => $members,
            'debts' => $memberLedger->debtByMember($members),
        ]);
    }

    private function findMember(string $memberId): Member
    {
        return Member::query()->findOrFail($memberId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingMemberId', 'showForm', 'name', 'phone', 'note', 'actionError']);
    }
}
