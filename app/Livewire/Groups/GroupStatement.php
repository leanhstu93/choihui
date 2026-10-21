<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\HuiGroup;
use App\Queries\ProfitReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Phiếu tổng kết một dây để xuất thành ảnh và gửi cho người khác (thay cho xuất CSV).
 * Ảnh được tạo trên trình duyệt (resources/js/app.js, imageExport).
 */
#[Layout('layouts.app')]
class GroupStatement extends Component
{
    #[Locked]
    public HuiGroup $group;

    public function mount(HuiGroup $group): void
    {
        Gate::authorize('view', $group);
        $this->group = $group;
    }

    public function render(ProfitReport $profitReport): View
    {
        $this->group->load(['slots.wonRound', 'rounds.cashEntries.slot', 'rounds.winnerSlot']);

        return view('livewire.groups.group-statement', [
            'summary' => $profitReport->forGroup($this->group),
            'exportedAt' => now(),
            'fileName' => 'so-hui-'.Str::slug($this->group->name).'-'.now()->format('Y-m-d').'.png',
        ])->title('Phiếu tổng kết · '.$this->group->name);
    }
}
