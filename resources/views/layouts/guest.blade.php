<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <div class="min-h-screen flex flex-col items-center px-4 pt-12 sm:justify-center sm:pt-0">
            <a href="/" wire:navigate class="flex flex-col items-center gap-2">
                <img src="/icons/icon-192.png" alt="" class="h-16 w-16">
                <span class="text-lg font-semibold text-emerald-800">{{ config('app.name') }}</span>
            </a>

            <div class="mt-6 w-full max-w-md rounded-xl bg-white px-6 py-5 shadow-sm">
                {{ $slot }}
            </div>
        </div>
        <x-cookie-consent />
    </body>
</html>
