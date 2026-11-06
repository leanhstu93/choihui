<?php

declare(strict_types=1);

namespace App\Livewire\Gold;

use App\Actions\Gold\DeleteGoldItem;
use App\Actions\Gold\DeleteGoldSale;
use App\Actions\Gold\SellGold;
use App\Domain\GoldLedger;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreGoldSaleRequest;
use App\Models\GoldItem;
use App\Models\GoldSale;
use App\Models\User;
use App\Queries\GoldPortfolio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Chi tiết một món vàng, bán, xóa giao dịch bán (document/06-vang.md VG-03..VG-05, VG-08).
 */
#[Layout('layouts.app')]
#[Title('Món vàng')]
class GoldItemShow extends Component
{
    public GoldItem $goldItem;

    public bool $showSell = false;

    public string $saleWeightChi = '';

    public ?int $saleAmount = null;

    public ?string $soldOn = null;

    public string $saleNote = '';

    public ?string $actionError = null;

    public function mount(GoldItem $goldItem): void
    {
        $this->authorize('view', $goldItem);
        $this->goldItem = $goldItem;
    }

    public function openSell(): void
    {
        $this->resetValidation();
        $this->fill(['showSell' => true, 'saleWeightChi' => '', 'saleAmount' => null, 'soldOn' => now()->toDateString(), 'saleNote' => '', 'actionError' => null]);
    }

    public function cancelSell(): void
    {
        $this->resetValidation();
        $this->showSell = false;
    }

    public function sell(SellGold $sellGold): void
    {
        $this->authorize('update', $this->goldItem);
        $this->actionError = null;

        $validated = StoreGoldSaleRequest::makeValidator([
            'weight_ly' => GoldLedger::parseChi($this->saleWeightChi),
            'amount' => $this->saleAmount,
            'sold_on' => $this->soldOn,
            'note' => trim($this->saleNote) ?: null,
        ])->validate();

        try {
            $sellGold->handle($this->goldItem, $validated);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->showSell = false;
    }

    public function deleteSale(string $saleId, DeleteGoldSale $deleteGoldSale): void
    {
        $sale = GoldSale::query()->where('gold_item_id', $this->goldItem->id)->findOrFail($saleId);
        $this->authorize('delete', $sale);
        $deleteGoldSale->handle($sale);
    }

    public function deleteItem(DeleteGoldItem $deleteGoldItem): void
    {
        $this->actionError = null;
        $response = Gate::inspect('delete', $this->goldItem);

        if ($response->denied()) {
            $this->actionError = $response->message();

            return;
        }

        try {
            $deleteGoldItem->handle($this->goldItem);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->redirectRoute('gold.index', navigate: true);
    }

    public function render(GoldPortfolio $portfolio): View
    {
        /** @var User $user */
        $user = Auth::user();
        $this->goldItem->load(['sales' => fn ($query) => $query->orderByDesc('sold_on')->orderByDesc('id')]);

        return view('livewire.gold.gold-item-show', ['position' => $portfolio->position($user, $this->goldItem)]);
    }
}
