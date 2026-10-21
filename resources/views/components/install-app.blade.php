{{-- Thẻ "Cài app" trên trang chủ: chỉ hiện trên điện thoại, chưa cài, chưa bấm "Để sau" (14 ngày). --}}
<div x-data="installApp" x-cloak wire:ignore>
    <section x-show="platform" class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-white p-3 shadow-sm">
        <img src="/icons/icon-192.png" alt="" class="h-11 w-11 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1 text-sm">
            <div class="font-semibold">Cài Sổ hụi lên màn hình chính</div>
            <div class="text-gray-500" x-text="platform === 'in-app' ? 'Mở bằng trình duyệt để cài' : 'Mở nhanh như app, không cần tải'"></div>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-1">
            <button type="button" @click="install" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white">Cài app</button>
            <button type="button" @click="dismiss" class="text-xs text-gray-400">Để sau</button>
        </div>
    </section>

    {{-- Hướng dẫn khi trình duyệt không tự mở hộp cài đặt --}}
    <div x-show="showGuide" x-transition.opacity class="fixed inset-0 z-50 flex items-end bg-black/50" @click.self="showGuide = false" role="dialog" aria-modal="true" aria-label="Hướng dẫn cài app">
        <div class="w-full rounded-t-3xl bg-white p-5 pb-8" style="padding-bottom: calc(2rem + env(safe-area-inset-bottom))">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Cài Sổ hụi</h2>
                <button type="button" @click="showGuide = false" class="text-2xl leading-none text-gray-400" aria-label="Đóng">&times;</button>
            </div>

            <ol x-show="platform === 'ios'" class="mt-4 space-y-3 text-base">
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">1</span><span>Bấm nút <strong>Chia sẻ</strong> <svg class="inline h-5 w-5 align-text-bottom text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0-12-4 4m4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg> ở thanh dưới</span></li>
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">2</span><span>Chọn <strong>Thêm vào MH chính</strong> <span class="inline-flex h-5 w-5 items-center justify-center rounded border border-gray-400 text-xs">+</span></span></li>
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">3</span><span>Bấm <strong>Thêm</strong> ở góc trên</span></li>
            </ol>
            <ol x-show="platform === 'android-manual'" class="mt-4 space-y-3 text-base">
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">1</span><span>Bấm <strong>⋮</strong> ở góc trên bên phải</span></li>
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">2</span><span>Chọn <strong>Thêm vào màn hình chính</strong> hoặc <strong>Cài đặt ứng dụng</strong></span></li>
            </ol>
            <ol x-show="platform === 'in-app'" class="mt-4 space-y-3 text-base">
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">1</span><span>Bấm <strong>⋯</strong> ở góc trên bên phải</span></li>
                <li class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-800">2</span><span>Chọn <strong>Mở bằng trình duyệt</strong>, rồi bấm lại "Cài app"</span></li>
            </ol>

            <p x-show="platform === 'ios'" class="mt-6 flex flex-col items-center text-sm text-gray-500">
                Nút Chia sẻ nằm ở dưới cùng màn hình
                <svg class="mt-1 h-6 w-6 animate-bounce text-emerald-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m0 0-6-6m6 6 6-6"/></svg>
            </p>
        </div>
    </div>
</div>
