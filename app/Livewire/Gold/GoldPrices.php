<?php

declare(strict_types=1);

namespace App\Livewire\Gold;

use App\Actions\Gold\SaveGoldPrice;
use App\Enums\GoldType;
use App\Http\Requests\UpdateGoldPriceRequest;
use App\Models\GoldPrice;
use App\Models\User;
use App\Queries\GoldPortfolio;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Nhập giá hiện tại của từng loại vàng (document/06-vang.md VG-06).
 */
#[Layout('layouts.app')]
#[Title('Giá vàng')]
class GoldPrices extends Component
{
    /** @var array<string, int|null> Giá đang nhập, khóa là GoldType. */
    public array $prices = [];

    public function mount(): void
    {
        $saved = GoldPrice::query()->pluck('price_per_chi', 'gold_type');

        foreach (GoldType::cases() as $type) {
            $this->prices[$type->value] = $saved->get($type->value);
        }
    }

    public function savePrice(string $goldType, SaveGoldPrice $saveGoldPrice): void
    {
        $validated = UpdateGoldPriceRequest::makeValidator([
            'gold_type' => $goldType,
            'price_per_chi' => $this->prices[$goldType] ?? null,
        ])->validate();

        $saveGoldPrice->handle(GoldType::from($validated['gold_type']), $validated['price_per_chi']);
        $this->resetValidation();
    }

    public function render(GoldPortfolio $portfolio): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.gold.gold-prices', ['types' => $portfolio->forUser($user)['types']]);
    }
}
