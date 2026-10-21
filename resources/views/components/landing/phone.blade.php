@props(['label' => 'Màn hình ứng dụng Sổ hụi'])

{{-- Khung điện thoại cho ảnh minh họa giao diện trên trang giới thiệu. --}}
<div {{ $attributes->merge(['class' => 'relative mx-auto w-[260px] rounded-[2.5rem] border-[10px] border-gray-900 bg-gray-900 shadow-2xl shadow-emerald-900/30']) }} role="img" aria-label="{{ $label }}">
    <div class="absolute left-1/2 top-0 z-10 h-5 w-24 -translate-x-1/2 rounded-b-2xl bg-gray-900"></div>
    <div class="min-h-[440px] overflow-hidden rounded-[1.9rem] bg-gray-100 pb-3" aria-hidden="true">
        <div class="flex h-11 items-end justify-between bg-emerald-700 px-4 pb-2 text-[11px] font-semibold text-white">
            <span>Sổ hụi</span>
            <span>9:41</span>
        </div>
        <div class="space-y-2 p-3 text-[11px] leading-tight">
            {{ $slot }}
        </div>
    </div>
</div>
