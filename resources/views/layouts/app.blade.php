<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <div class="min-h-screen pb-24">
            <livewire:layout.navigation />

            <main class="max-w-2xl mx-auto px-4 py-4">
                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        <x-bottom-nav />
        <x-cookie-consent />
    </body>
</html>
