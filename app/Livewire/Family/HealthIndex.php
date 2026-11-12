<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Models\User;
use App\Queries\HealthBook;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tổng quan sổ sức khỏe (document/09-gia-dinh.md GD-05).
 */
#[Layout('layouts.app')]
#[Title('Sức khỏe')]
class HealthIndex extends Component
{
    public function render(HealthBook $book): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.family.health-index', $book->forUser($user) + [
            'today' => now()->toImmutable()->startOfDay(),
        ]);
    }
}
