<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Actions\Health\DeleteHealthPerson;
use App\Models\HealthPerson;
use App\Queries\HealthBook;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lịch sử khám của một người (document/09-gia-dinh.md GD-07).
 */
#[Layout('layouts.app')]
#[Title('Sức khỏe')]
class PersonShow extends Component
{
    public HealthPerson $healthPerson;

    public function mount(HealthPerson $healthPerson): void
    {
        $this->authorize('view', $healthPerson);
        $this->healthPerson = $healthPerson;
    }

    public function deletePerson(DeleteHealthPerson $deleteHealthPerson): void
    {
        $this->authorize('delete', $this->healthPerson);
        $deleteHealthPerson->handle($this->healthPerson);

        $this->redirectRoute('family.health.index', navigate: true);
    }

    public function render(HealthBook $book): View
    {
        return view('livewire.family.person-show', $book->forPerson($this->healthPerson) + [
            'today' => now()->toImmutable()->startOfDay(),
        ]);
    }
}
