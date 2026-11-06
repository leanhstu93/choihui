<?php

declare(strict_types=1);

namespace App\Livewire\Gold;

use App\Actions\Gold\SaveGoldItem;
use App\Domain\GoldLedger;
use App\Enums\GoldType;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreGoldItemRequest;
use App\Models\GoldItem;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Thêm hoặc sửa một món vàng (document/06-vang.md VG-01, VG-02).
 */
#[Layout('layouts.app')]
#[Title('Món vàng')]
class GoldItemForm extends Component
{
    #[Locked]
    public ?string $itemId = null;

    public string $goldType = 'nhan_9999';

    public string $name = '';

    public string $weightChi = '';

    public ?int $costAmount = null;

    public ?string $boughtOn = null;

    public string $shop = '';

    public string $note = '';

    public ?string $actionError = null;

    public function mount(?GoldItem $goldItem = null): void
    {
        $this->boughtOn = now()->toDateString();

        if ($goldItem?->exists) {
            $this->authorize('update', $goldItem);
            $this->fill([
                'itemId' => $goldItem->id,
                'goldType' => $goldItem->gold_type->value,
                'name' => (string) $goldItem->name,
                'weightChi' => GoldLedger::formatChi($goldItem->weight_ly),
                'costAmount' => $goldItem->cost_amount,
                'boughtOn' => $goldItem->bought_on->toDateString(),
                'shop' => (string) $goldItem->shop,
                'note' => (string) $goldItem->note,
            ]);
        }
    }

    public function save(SaveGoldItem $saveGoldItem): void
    {
        $item = $this->itemId === null ? null : GoldItem::query()->findOrFail($this->itemId);

        if ($item !== null) {
            $this->authorize('update', $item);
        }

        $this->actionError = null;
        $validated = StoreGoldItemRequest::makeValidator([
            'gold_type' => $this->goldType,
            'name' => trim($this->name) ?: null,
            'weight_ly' => GoldLedger::parseChi($this->weightChi),
            'cost_amount' => $this->costAmount,
            'bought_on' => $this->boughtOn,
            'shop' => trim($this->shop) ?: null,
            'note' => trim($this->note) ?: null,
        ])->validate();

        try {
            $saved = $saveGoldItem->handle($item, $validated);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->redirectRoute('gold.show', $saved, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.gold.gold-item-form', ['types' => GoldType::cases()]);
    }
}
