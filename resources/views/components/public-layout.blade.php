@props(['title', 'description', 'canonical'])

{{-- Khung trang công khai (không cần đăng nhập): thanh trên, chân trang, Alpine qua Livewire. --}}
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => $title])
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ asset('images/og-image.png') }}">
        {{ $head ?? '' }}
    </head>
    <body class="bg-rose-50/40 font-sans text-gray-900 antialiased">
        <x-public-header />

        <main class="mx-auto max-w-5xl px-4 py-8">
            {{ $slot }}
        </main>

        <footer class="px-4 py-6 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ config('app.name') }} · Âm lịch tính theo giờ Việt Nam. Ngày tốt xấu chỉ để tham khảo.
            · <a href="{{ route('privacy') }}" class="underline hover:text-gray-600">Chính sách bảo mật</a>
        </footer>

        @livewireScripts
        <x-cookie-consent />
    </body>
</html>
