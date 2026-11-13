{{-- Mời đăng ký: ghi ngày giỗ, sinh nhật để app tự nhắc --}}
<section class="mt-10 rounded-3xl bg-emerald-700 p-6 text-center text-white sm:p-8">
    <div class="text-4xl" aria-hidden="true">🕯️</div>
    <h2 class="mt-2 text-xl font-bold sm:text-2xl">Nhà bạn có ngày giỗ, sinh nhật theo âm lịch?</h2>
    <p class="mx-auto mt-2 max-w-xl text-emerald-50">Ghi một lần vào {{ config('app.name') }}, năm nào app cũng tự đổi sang ngày dương và gửi email nhắc trước. Miễn phí.</p>
    @auth
        <a href="{{ route('family.events.create') }}" class="mt-5 inline-block rounded-full bg-white px-6 py-3 font-semibold text-emerald-800 hover:bg-emerald-50">Thêm ngày nhớ →</a>
    @else
        <a href="{{ auth()->check() ? route('net-worth') : route('register') }}" class="mt-5 inline-block rounded-full bg-white px-6 py-3 font-semibold text-emerald-800 hover:bg-emerald-50">{{ auth()->check() ? 'Vào sổ của bạn →' : 'Dùng miễn phí →' }}</a>
    @endauth
</section>
