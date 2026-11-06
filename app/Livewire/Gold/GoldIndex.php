<?php

declare(strict_types=1);

namespace App\Livewire\Gold;

use App\Models\User;
use App\Queries\GoldPortfolio;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tổng quan sổ vàng (document/06-vang.md VG-07): tổng hợp, danh sách món.
 */
#[Layout('layouts.app')]
#[Title('Sổ vàng')]
class GoldIndex extends Component
{
    public function render(GoldPortfolio $portfolio): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.gold.gold-index', $portfolio->forUser($user));
    }
}
