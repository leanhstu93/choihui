@props(['savedPhotos', 'photos', 'photoRoute', 'label' => 'Ảnh', 'hint' => null])

{{-- Ô chọn ảnh dùng chung; form cha đặt x-data="photoPicker" và khai báo thuộc tính $photos. --}}
<x-field :label="$label" name="photos" :hint="$hint">
    @if ($savedPhotos->isNotEmpty() || $photos !== [])
        <div class="mb-2 grid grid-cols-3 gap-2">
            @foreach ($savedPhotos as $photo)
                <div wire:key="saved-{{ $photo->id }}">
                    <img src="{{ route($photoRoute, $photo) }}" alt="Ảnh đã lưu" class="aspect-square w-full rounded-lg bg-gray-200 object-cover">
                    <button type="button" wire:click="deletePhoto('{{ $photo->id }}')" wire:confirm="Xóa ảnh này?" class="mt-1 w-full text-center text-sm text-red-600">Xóa</button>
                </div>
            @endforeach
            @foreach ($photos as $index => $photo)
                @if ($photo->isPreviewable())
                    <img src="{{ $photo->temporaryUrl() }}" alt="Ảnh mới" wire:key="new-{{ $index }}" class="aspect-square w-full rounded-lg bg-gray-200 object-cover">
                @endif
            @endforeach
        </div>
    @endif
    <input id="photos" type="file" accept="image/jpeg,image/png,image/webp" multiple x-on:change="pick($event)" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm">
    <p x-show="busy" x-cloak class="mt-1 text-sm text-gray-500">Đang tải ảnh...</p>
    <p x-show="failed" x-cloak class="mt-1 text-sm text-red-600">Không tải được ảnh, vui lòng thử lại.</p>
    @error('photos.*')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</x-field>
