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
        <header class="sticky top-0 z-30 border-b border-emerald-900/10 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2 font-semibold text-emerald-800">
                    <img src="/icons/icon-192.png" alt="" class="h-8 w-8 rounded-lg"> {{ config('app.name') }}
                </a>
                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ route('lunar.today') }}" class="hidden font-medium text-gray-600 hover:text-emerald-700 sm:inline">Lịch âm</a>
                    <a href="{{ route('transfer.show') }}" class="hidden font-medium text-gray-600 hover:text-emerald-700 sm:inline">Gửi file</a>
                    @auth
                        <a href="{{ route('net-worth') }}" class="rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Vào sổ</a>
                    @else
                        <a href="{{ route('login') }}" class="font-medium text-gray-600 hover:text-emerald-700">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Dùng miễn phí</a>
                    @endauth
                </nav>
            </div>
        </header>

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
