<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Actions\Health\AddHealthRecordPhotos;
use App\Actions\Health\DeleteHealthRecord;
use App\Actions\Health\DeleteHealthRecordPhoto;
use App\Actions\Health\SaveHealthRecord;
use App\Enums\HealthRecordKind;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreHealthRecordRequest;
use App\Http\Requests\StorePhotosRequest;
use App\Models\HealthPerson;
use App\Models\HealthRecord;
use App\Models\HealthRecordPhoto;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Thêm hoặc sửa một lần khám (document/09-gia-dinh.md GD-06).
 */
#[Layout('layouts.app')]
#[Title('Lần khám')]
class RecordForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $personId;

    #[Locked]
    public ?string $recordId = null;

    public ?string $visitedOn = null;

    public string $kind = 'kham';

    public string $title = '';

    public string $place = '';

    public ?int $amount = null;

    public ?string $followUpOn = null;

    public string $note = '';

    /** @var array<int, TemporaryUploadedFile> Ảnh mới, chưa lưu. */
    public array $photos = [];

    public ?string $actionError = null;

    public function mount(?HealthPerson $healthPerson = null, ?HealthRecord $healthRecord = null): void
    {
        $this->visitedOn = now()->toDateString();

        if ($healthRecord?->exists) {
            $this->authorize('update', $healthRecord);
            $this->fill([
                'personId' => $healthRecord->health_person_id,
                'recordId' => $healthRecord->id,
                'visitedOn' => $healthRecord->visited_on->toDateString(),
                'kind' => $healthRecord->kind->value,
                'title' => $healthRecord->title,
                'place' => (string) $healthRecord->place,
                'amount' => $healthRecord->amount,
                'followUpOn' => $healthRecord->follow_up_on?->toDateString(),
                'note' => (string) $healthRecord->note,
            ]);

            return;
        }

        abort_if($healthPerson === null || ! $healthPerson->exists, 404);
        $this->authorize('update', $healthPerson);
        $this->personId = $healthPerson->id;
    }

    public function deletePhoto(string $photoId, DeleteHealthRecordPhoto $deleteHealthRecordPhoto): void
    {
        $photo = HealthRecordPhoto::query()->where('health_record_id', $this->recordId)->findOrFail($photoId);
        $this->authorize('delete', $photo);
        $deleteHealthRecordPhoto->handle($photo);
    }

    public function deleteRecord(DeleteHealthRecord $deleteHealthRecord): void
    {
        $record = HealthRecord::query()->findOrFail($this->recordId);
        $this->authorize('delete', $record);
        $deleteHealthRecord->handle($record);

        $this->redirectRoute('family.health.show', $this->personId, navigate: true);
    }

    public function save(SaveHealthRecord $saveHealthRecord, AddHealthRecordPhotos $addHealthRecordPhotos): void
    {
        $person = HealthPerson::query()->findOrFail($this->personId);
        $this->authorize('update', $person);
        $record = $this->recordId === null ? null : HealthRecord::query()->findOrFail($this->recordId);

        if ($record !== null) {
            $this->authorize('update', $record);
        }

        $this->actionError = null;
        $remaining = HealthRecord::MAX_PHOTOS - ($record?->photos()->count() ?? 0);
        StorePhotosRequest::makeValidator(['photos' => $this->photos], $remaining, HealthRecord::MAX_PHOTOS)->validate();

        $validated = StoreHealthRecordRequest::makeValidator([
            'visited_on' => $this->visitedOn,
            'kind' => $this->kind,
            'title' => trim($this->title),
            'place' => trim($this->place) ?: null,
            'amount' => $this->amount,
            'follow_up_on' => $this->followUpOn ?: null,
            'note' => trim($this->note) ?: null,
        ])->validate();

        try {
            $saved = $saveHealthRecord->handle($person, $record, $validated);
            $addHealthRecordPhotos->handle($saved, $this->photos);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->redirectRoute('family.health.show', $person, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.family.record-form', [
            'person' => HealthPerson::query()->findOrFail($this->personId),
            'kinds' => HealthRecordKind::cases(),
            'savedPhotos' => $this->recordId === null ? collect() : HealthRecordPhoto::query()->where('health_record_id', $this->recordId)->oldest('id')->get(),
        ]);
    }
}
