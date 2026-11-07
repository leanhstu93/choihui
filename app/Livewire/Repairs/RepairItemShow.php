<?php

declare(strict_types=1);

namespace App\Livewire\Repairs;

use App\Actions\Repairs\DeleteRepairItem;
use App\Models\RepairItem;
use App\Queries\RepairHistory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lịch sử sửa chữa của một món đồ (document/08-sua-chua.md SC-04, SC-05).
 */
#[Layout('layouts.app')]
#[Title('Món đồ')]
class RepairItemShow extends Component
{
    public RepairItem $repairItem;

    public function mount(RepairItem $repairItem): void
    {
        $this->authorize('view', $repairItem);
        $this->repairItem = $repairItem;
    }

    public function deleteItem(DeleteRepairItem $deleteRepairItem): void
    {
        $this->authorize('delete', $this->repairItem);
        $deleteRepairItem->handle($this->repairItem);

        $this->redirectRoute('repairs.index', navigate: true);
    }

    public function render(RepairHistory $history): View
    {
        return view('livewire.repairs.repair-item-show', $history->forItem($this->repairItem) + [
            'today' => now()->toImmutable()->startOfDay(),
        ]);
    }
}
