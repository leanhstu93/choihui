@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <h1 class="text-xl font-semibold">Cài đặt</h1>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Nhắc đến hạn qua email</h2>
        <form wire:submit="saveReminder" class="mt-3 space-y-3">
            <x-field label="Nhắc trước bao nhiêu ngày" name="reminderDaysBefore" hint="Nhập 0 để tắt email nhắc.">
                <input id="reminderDaysBefore" type="number" inputmode="numeric" min="0" max="30" wire:model="reminderDaysBefore" class="{{ $inputClass }}">
            </x-field>
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Lưu</button>
        </form>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Khóa bằng mã PIN</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ $hasPin ? 'Đang bật.' : 'Đang tắt.' }} Ứng dụng tự khóa sau {{ config('hui.pin_idle_minutes') }} phút không dùng.
        </p>
        <form wire:submit="savePin" class="mt-3 space-y-3">
            @if ($hasPin)
                <x-field label="Mã PIN hiện tại" name="currentPin">
                    <input id="currentPin" type="password" inputmode="numeric" maxlength="6" wire:model="currentPin" class="{{ $inputClass }}">
                </x-field>
            @endif
            <div class="grid grid-cols-2 gap-3">
                <x-field label="{{ $hasPin ? 'Mã PIN mới' : 'Mã PIN' }}" name="newPin">
                    <input id="newPin" type="password" inputmode="numeric" maxlength="6" wire:model="newPin" class="{{ $inputClass }}">
                </x-field>
                <x-field label="Nhập lại" name="newPinConfirmation">
                    <input id="newPinConfirmation" type="password" inputmode="numeric" maxlength="6" wire:model="newPinConfirmation" class="{{ $inputClass }}">
                </x-field>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">{{ $hasPin ? 'Đổi PIN' : 'Bật khóa PIN' }}</button>
                @if ($hasPin)
                    <button type="button" wire:click="removePin" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Tắt khóa PIN</button>
                @endif
            </div>
        </form>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Sao lưu dữ liệu</h2>
        <p class="mt-1 text-sm text-gray-600">Tải toàn bộ dây hụi về một file để cất giữ, hoặc khôi phục từ file đã tải.</p>
        <a href="{{ route('settings.backup') }}" download class="mt-3 inline-flex rounded-lg border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700">Tải file sao lưu</a>

        <form wire:submit="restoreBackup" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
            <x-field label="Khôi phục từ file" name="backupFile">
                <input id="backupFile" type="file" accept=".json,application/json" wire:model="backupFile" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2">
            </x-field>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" wire:model="confirmRestore" class="mt-0.5 rounded border-gray-300 text-emerald-700">
                <span>Tôi hiểu toàn bộ dây hụi hiện có sẽ bị <strong>thay thế</strong> bằng dữ liệu trong file.</span>
            </label>
            @error('confirmRestore') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" wire:loading.attr="disabled" wire:target="backupFile,restoreBackup">Khôi phục</button>
        </form>
    </section>

    <section class="rounded-xl bg-white shadow-sm divide-y divide-gray-100 text-sm">
        <a href="{{ route('profile') }}" wire:navigate class="flex justify-between p-4"><span>Tài khoản và mật khẩu</span><span class="text-gray-400">&rsaquo;</span></a>
        <button type="button" wire:click="logout" class="w-full p-4 text-start text-red-600">Đăng xuất</button>
    </section>
</div>
