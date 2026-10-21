<div class="space-y-4">
    <div class="text-center">
        <h1 class="text-lg font-semibold">Nhập mã PIN</h1>
        <p class="text-sm text-gray-600">Ứng dụng đã khóa do không sử dụng một lúc.</p>
    </div>

    <form wire:submit="unlock" class="space-y-3">
        <input type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="pin" autofocus aria-label="Mã PIN"
               class="w-full rounded-lg border-gray-300 text-center text-2xl tracking-[0.5em] focus:border-emerald-600 focus:ring-emerald-600">
        @error('pin') <p class="text-center text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="w-full rounded-lg bg-emerald-700 py-3 font-semibold text-white">Mở khóa</button>
    </form>

    <button type="button" wire:click="logout" class="w-full text-center text-sm text-gray-600">Quên PIN? Đăng xuất và đăng nhập lại</button>
</div>
