<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Actions\Health\SaveHealthPerson;
use App\Http\Requests\StoreHealthPersonRequest;
use App\Models\HealthPerson;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Thêm hoặc sửa một người trong sổ sức khỏe (document/09-gia-dinh.md GD-04).
 */
#[Layout('layouts.app')]
#[Title('Người thân')]
class PersonForm extends Component
{
    #[Locked]
    public ?string $personId = null;

    public string $name = '';

    public string $birthYear = '';

    public string $note = '';

    public function mount(?HealthPerson $healthPerson = null): void
    {
        if ($healthPerson?->exists) {
            $this->authorize('update', $healthPerson);
            $this->fill([
                'personId' => $healthPerson->id,
                'name' => $healthPerson->name,
                'birthYear' => (string) $healthPerson->birth_year,
                'note' => (string) $healthPerson->note,
            ]);
        }
    }

    public function save(SaveHealthPerson $saveHealthPerson): void
    {
        $person = $this->personId === null ? null : HealthPerson::query()->findOrFail($this->personId);

        if ($person !== null) {
            $this->authorize('update', $person);
        }

        $birthYear = trim($this->birthYear);

        $validated = StoreHealthPersonRequest::makeValidator([
            'name' => trim($this->name),
            'birth_year' => $birthYear === '' ? null : (ctype_digit($birthYear) ? (int) $birthYear : $birthYear),
            'note' => trim($this->note) ?: null,
        ])->validate();

        $saved = $saveHealthPerson->handle($person, $validated);

        $this->redirectRoute('family.health.show', $saved, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.family.person-form');
    }
}
