<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Models\Member;
use App\Queries\MemberLedger;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chi tiết một hụi viên (CH-09): các tay đang giữ ở mọi dây, đã đóng, còn nợ.
 */
#[Layout('layouts.app')]
class MemberShow extends Component
{
    #[Locked]
    public Member $member;

    public function mount(Member $member): void
    {
        Gate::authorize('view', $member);
        $this->member = $member;
    }

    public function render(MemberLedger $memberLedger): View
    {
        return view('livewire.members.member-show', [
            'ledger' => $memberLedger->forMember($this->member),
        ])->title($this->member->name);
    }
}
