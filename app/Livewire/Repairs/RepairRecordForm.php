<?php

declare(strict_types=1);

namespace App\Livewire\Repairs;

use App\Actions\Repairs\AddRepairRecordPhotos;
use App\Actions\Repairs\DeleteRepairRecord;
use App\Actions\Repairs\DeleteRepairRecordPhoto;
use App\Actions\Repairs\SaveRepairRecord;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StorePhotosRequest;
use App\Http\Requests\StoreRepairRecordRequest;
use App\Models\RepairItem;
use App\Models\RepairRecord;
use App\Models\RepairRecordPhoto;
use App\Queries\RepairHistory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Thêm hoặc sửa một lần sửa (document/08-sua-chua.md SC-03).
 */
#[Layout('layouts.app')]
#[Title('Lần sửa')]
class RepairRecordForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $itemId;

    #[Locked]
    public ?string $recordId = null;

    public ?string $repairedOn = null;

    public string $part = '';

    public ?int $amount = null;

    public string $place = '';

    public ?string $warrantyUntil = null;

    public string $odometerKm = '';

    public string $note = '';

    /** @var array<int, TemporaryUploadedFile> Ảnh mới, chưa lưu. */
    public array $photos = [];

    public ?string $actionError = null;

    public function mount(?RepairItem $repairItem = null, ?RepairRecord $repairRecord = null): void
    {
        $this->repairedOn = now()->toDateString();

        if ($repairRecord?->exists) {
            $this->authorize('update', $repairRecord);
            $this->fill([
                'itemId' => $repairRecord->repair_item_id,
                'recordId' => $repairRecord->id,
                'repairedOn' => $repairRecord->repaired_on->toDateString(),
                'part' => $repairRecord->part,
                'amount' => $repairRecord->amount,
                'place' => (string) $repairRecord->place,
                'warrantyUntil' => $repairRecord->warranty_until?->toDateString(),
                'odometerKm' => (string) $repairRecord->odometer_km,
                'note' => (string) $repairRecord->note,
            ]);

            return;
        }

        abort_if($repairItem === null || ! $repairItem->exists, 404);
        $this->authorize('update', $repairItem);
        $this->itemId = $repairItem->id;
    }

    public function deletePhoto(string $photoId, DeleteRepairRecordPhoto $deleteRepairRecordPhoto): void
    {
        $photo = RepairRecordPhoto::query()->where('repair_record_id', $this->recordId)->findOrFail($photoId);
        $this->authorize('delete', $photo);
        $deleteRepairRecordPhoto->handle($photo);
    }

    public function deleteRecord(DeleteRepairRecord $deleteRepairRecord): void
    {
        $record = RepairRecord::query()->findOrFail($this->recordId);
        $this->authorize('delete', $record);
        $deleteRepairRecord->handle($record);

        $this->redirectRoute('repairs.show', $this->itemId, navigate: true);
    }

    public function save(SaveRepairRecord $saveRepairRecord, AddRepairRecordPhotos $addRepairRecordPhotos): void
    {
        $item = RepairItem::query()->findOrFail($this->itemId);
        $this->authorize('update', $item);
        $record = $this->recordId === null ? null : RepairRecord::query()->findOrFail($this->recordId);

        if ($record !== null) {
            $this->authorize('update', $record);
        }

        $this->actionError = null;
        $remaining = RepairRecord::MAX_PHOTOS - ($record?->photos()->count() ?? 0);
        StorePhotosRequest::makeValidator(['photos' => $this->photos], $remaining, RepairRecord::MAX_PHOTOS)->validate();

        $digits = preg_replace('/\D/', '', $this->odometerKm);

        $validated = StoreRepairRecordRequest::makeValidator([
            'repaired_on' => $this->repairedOn,
            'part' => trim($this->part),
            'amount' => $this->amount,
            'place' => trim($this->place) ?: null,
            'warranty_until' => $this->warrantyUntil ?: null,
            'odometer_km' => $digits === '' || $digits === null ? null : (int) $digits,
            'note' => trim($this->note) ?: null,
        ])->validate();

        try {
            $saved = $saveRepairRecord->handle($item, $record, $validated);
            $addRepairRecordPhotos->handle($saved, $this->photos);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->redirectRoute('repairs.show', $item, navigate: true);
    }

    public function render(RepairHistory $history): View
    {
        $item = RepairItem::query()->findOrFail($this->itemId);

        return view('livewire.repairs.repair-record-form', [
            'item' => $item,
            'partNames' => $history->partNames($item),
            'showKm' => $item->tracks_km || $this->odometerKm !== '',
            'savedPhotos' => $this->recordId === null ? collect() : RepairRecordPhoto::query()->where('repair_record_id', $this->recordId)->oldest('id')->get(),
        ]);
    }
}
