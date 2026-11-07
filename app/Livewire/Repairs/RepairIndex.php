<?php

declare(strict_types=1);

namespace App\Livewire\Repairs;

use App\Models\User;
use App\Queries\RepairHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tổng quan sổ sửa chữa (document/08-sua-chua.md SC-02).
 */
#[Layout('layouts.app')]
#[Title('Sổ sửa chữa')]
class RepairIndex extends Component
{
    public function render(RepairHistory $history): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.repairs.repair-index', $history->forUser($user));
    }
}
