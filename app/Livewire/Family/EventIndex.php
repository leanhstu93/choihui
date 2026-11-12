<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Models\FamilyEvent;
use App\Models\User;
use App\Queries\FamilyAgenda;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Danh sách ngày nhớ, lần tới gần nhất lên đầu (document/09-gia-dinh.md GD-02).
 */
#[Layout('layouts.app')]
#[Title('Ngày nhớ')]
class EventIndex extends Component
{
    public function render(FamilyAgenda $agenda): View
    {
        /** @var User $user */
        $user = Auth::user();
        $today = now()->toImmutable()->startOfDay();

        return view('livewire.family.event-index', [
            'today' => $today,
            'todayLunar' => FamilyEvent::lunarLabel($today),
            'occurrences' => $agenda->upcoming($user, $today),
        ]);
    }
}
