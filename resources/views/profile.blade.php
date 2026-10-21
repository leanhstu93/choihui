<x-app-layout>
    <x-slot name="title">Tài khoản</x-slot>

    <div class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
            <livewire:profile.update-password-form />
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm sm:p-6">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
