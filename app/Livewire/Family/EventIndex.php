<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Models\FamilyEvent;
use App\Models\User;
use App\Queries\FamilyAgenda;
use App\Queries\FamilyOccurrence;
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
    /**
     * Số ngày tính là "sắp tới" để tách nhóm đầu danh sách.
     */
    private const SOON_DAYS = 30;

    public function render(FamilyAgenda $agenda): View
    {
        /** @var User $user */
        $user = Auth::user();
        $today = now()->toImmutable()->startOfDay();

        $occurrences = collect($agenda->upcoming($user, $today));
        [$soon, $later] = $occurrences->partition(fn (FamilyOccurrence $occurrence) => $occurrence->daysLeft <= self::SOON_DAYS);

        return view('livewire.family.event-index', [
            'today' => $today,
            'todayLunar' => FamilyEvent::lunarLabel($today),
            'hasEvents' => $occurrences->isNotEmpty(),
            'groups' => array_filter(['Trong 30 ngày tới' => $soon, 'Cả năm' => $later], fn ($group) => $group->isNotEmpty()),
        ]);
    }
}
