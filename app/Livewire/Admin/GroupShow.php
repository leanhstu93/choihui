<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\RecordAdminLog;
use App\Enums\AdminAction;
use App\Livewire\Concerns\AdminOnly;
use App\Models\HuiGroup;
use App\Queries\HostReport;
use App\Queries\ProfitReport;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Chi tiết một dây của người dùng, chỉ đọc (document/05-quan-tri.md QT-08).
 * Dây của người khác nên không dùng route model binding (bị global scope owner
 * chặn) mà tự nạp theo id, bỏ scope ở mọi quan hệ.
 */
#[Layout('layouts.app')]
#[Title('Dây hụi · Quản trị')]
class GroupShow extends Component
{
    use AdminOnly;

    #[Locked]
    public string $groupId;

    public function mount(string $groupId, RecordAdminLog $recordAdminLog): void
    {
        $group = $this->loadGroup($groupId);
        $this->groupId = $group->id;
        $recordAdminLog->handle($this->admin(), AdminAction::ViewGroup, $group->user, ['group_id' => $group->id, 'group_name' => $group->name]);
    }

    public function render(ProfitReport $profitReport, HostReport $hostReport): View
    {
        $group = $this->loadGroup($this->groupId);
        $isHost = $group->user->isHost();

        return view('livewire.admin.group-show', [
            'group' => $group,
            'owner' => $group->user,
            'isHost' => $isHost,
            'summary' => $isHost ? $hostReport->forGroup($group) : $profitReport->forGroup($group),
        ]);
    }

    private function loadGroup(string $groupId): HuiGroup
    {
        $withoutOwner = fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE);

        return HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->with([
                'user',
                'slots' => $withoutOwner,
                'hostSlots' => $withoutOwner,
                'hostSlots.member' => $withoutOwner,
                'rounds' => $withoutOwner,
                'rounds.winnerSlot' => $withoutOwner,
                'rounds.winnerHostSlot' => $withoutOwner,
                'rounds.winnerHostSlot.member' => $withoutOwner,
                'rounds.cashEntries' => $withoutOwner,
                'rounds.cashEntries.slot' => $withoutOwner,
                'rounds.hostEntries' => $withoutOwner,
                'rounds.hostEntries.hostSlot' => $withoutOwner,
                'rounds.hostEntries.hostSlot.member' => $withoutOwner,
            ])
            ->findOrFail($groupId);
    }
}
