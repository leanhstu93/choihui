<?php

declare(strict_types=1);

namespace App\Livewire\Gold;

use App\Actions\Gold\AddGoldItemPhotos;
use App\Actions\Gold\DeleteGoldItemPhoto;
use App\Actions\Gold\SaveGoldItem;
use App\Domain\GoldLedger;
use App\Enums\GoldType;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreGoldItemRequest;
use App\Http\Requests\StoreGoldPhotosRequest;
use App\Models\GoldItem;
use App\Models\GoldItemPhoto;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Thêm hoặc sửa một món vàng (document/06-vang.md VG-01, VG-02).
 */
#[Layout('layouts.app')]
#[Title('Món vàng')]
class GoldItemForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?string $itemId = null;

    public string $goldType = 'nhan_9999';

    public string $name = '';

    public string $weightChi = '';

    public ?int $costAmount = null;

    public ?string $boughtOn = null;

    public string $shop = '';

    public string $note = '';

    /** @var array<int, TemporaryUploadedFile> Ảnh mới, chưa lưu. */
    public array $photos = [];

    public ?string $actionError = null;

    public function mount(?GoldItem $goldItem = null): void
    {
        $this->boughtOn = now()->toDateString();

        if ($goldItem?->exists) {
            $this->authorize('update', $goldItem);
            $this->fill([
                'itemId' => $goldItem->id,
                'goldType' => $goldItem->gold_type->value,
                'name' => (string) $goldItem->name,
                'weightChi' => GoldLedger::formatChi($goldItem->weight_ly),
                'costAmount' => $goldItem->cost_amount,
                'boughtOn' => $goldItem->bought_on->toDateString(),
                'shop' => (string) $goldItem->shop,
                'note' => (string) $goldItem->note,
            ]);
        }
    }

    public function deletePhoto(string $photoId, DeleteGoldItemPhoto $deleteGoldItemPhoto): void
    {
        $photo = GoldItemPhoto::query()->where('gold_item_id', $this->itemId)->findOrFail($photoId);
        $this->authorize('delete', $photo);
        $deleteGoldItemPhoto->handle($photo);
    }

    public function save(SaveGoldItem $saveGoldItem, AddGoldItemPhotos $addGoldItemPhotos): void
    {
        $item = $this->itemId === null ? null : GoldItem::query()->findOrFail($this->itemId);

        if ($item !== null) {
            $this->authorize('update', $item);
        }

        $this->actionError = null;
        StoreGoldPhotosRequest::makeValidator(['photos' => $this->photos], $this->remainingPhotoSlots($item))->validate();

        $validated = StoreGoldItemRequest::makeValidator([
            'gold_type' => $this->goldType,
            'name' => trim($this->name) ?: null,
            'weight_ly' => GoldLedger::parseChi($this->weightChi),
            'cost_amount' => $this->costAmount,
            'bought_on' => $this->boughtOn,
            'shop' => trim($this->shop) ?: null,
            'note' => trim($this->note) ?: null,
        ])->validate();

        try {
            $saved = $saveGoldItem->handle($item, $validated);
            $addGoldItemPhotos->handle($saved, $this->photos);
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();

            return;
        }

        $this->redirectRoute('gold.show', $saved, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.gold.gold-item-form', [
            'types' => GoldType::cases(),
            'savedPhotos' => $this->itemId === null ? collect() : GoldItemPhoto::query()->where('gold_item_id', $this->itemId)->oldest('id')->get(),
        ]);
    }

    private function remainingPhotoSlots(?GoldItem $item): int
    {
        return GoldItem::MAX_PHOTOS - ($item?->photos()->count() ?? 0);
    }
}
