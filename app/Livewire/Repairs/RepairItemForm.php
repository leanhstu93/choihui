<?php

declare(strict_types=1);

namespace App\Livewire\Repairs;

use App\Actions\Repairs\SaveRepairItem;
use App\Http\Requests\StoreRepairItemRequest;
use App\Models\RepairItem;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Thêm hoặc sửa một món đồ (document/08-sua-chua.md SC-01).
 */
#[Layout('layouts.app')]
#[Title('Món đồ')]
class RepairItemForm extends Component
{
    #[Locked]
    public ?string $itemId = null;

    public string $name = '';

    public bool $tracksKm = false;

    public string $note = '';

    public function mount(?RepairItem $repairItem = null): void
    {
        if ($repairItem?->exists) {
            $this->authorize('update', $repairItem);
            $this->fill([
                'itemId' => $repairItem->id,
                'name' => $repairItem->name,
                'tracksKm' => $repairItem->tracks_km,
                'note' => (string) $repairItem->note,
            ]);
        }
    }

    public function save(SaveRepairItem $saveRepairItem): void
    {
        $item = $this->itemId === null ? null : RepairItem::query()->findOrFail($this->itemId);

        if ($item !== null) {
            $this->authorize('update', $item);
        }

        $validated = StoreRepairItemRequest::makeValidator([
            'name' => trim($this->name),
            'tracks_km' => $this->tracksKm,
            'note' => trim($this->note) ?: null,
        ])->validate();

        $saved = $saveRepairItem->handle($item, $validated);

        $this->redirectRoute('repairs.show', $saved, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.repairs.repair-item-form');
    }
}
