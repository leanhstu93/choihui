<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <main class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <img src="/icons/icon-192.png" alt="" class="h-20 w-20">
            <h1 class="mt-4 text-2xl font-semibold text-emerald-800">{{ config('app.name') }}</h1>
            <p class="mt-2 max-w-sm text-gray-600">Theo dõi các dây hụi bạn chơi: kỳ nào phải đóng bao nhiêu, bao giờ hốt, hốt được bao nhiêu và tổng lại lời hay lỗ.</p>

            <div class="mt-8 flex w-full max-w-xs flex-col gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-emerald-700 py-3 font-semibold text-white">Vào ứng dụng</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-emerald-700 py-3 font-semibold text-white">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="rounded-lg border border-emerald-700 py-3 font-semibold text-emerald-700">Tạo tài khoản</a>
                @endauth
            </div>
        </main>
    </body>
</html>
