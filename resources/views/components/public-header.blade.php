@props(['brand' => config('app.name')])

@php
    // Các công cụ dùng được không cần đăng nhập. Trang đang mở được tô đậm.
    $tools = [
        ['route' => 'lunar.today', 'active' => 'lunar.*', 'icon' => '📅', 'label' => 'Lịch âm', 'hint' => 'Tra ngày âm, ngày lễ'],
        ['route' => 'transfer.show', 'active' => 'transfer.*', 'icon' => '📤', 'label' => 'Gửi file', 'hint' => 'Gửi giữa hai thiết bị'],
    ];
@endphp

{{-- Thanh trên của các trang công khai: logo, công cụ miễn phí, đăng nhập. Trên điện thoại các công cụ
     nằm ở hàng thứ hai để luôn bấm được, không giấu trong menu. --}}
<header class="sticky top-0 z-30 border-b border-emerald-900/10 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-5xl items-center gap-2 px-4 py-2.5 sm:gap-6 sm:py-3">
        <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2 rounded-xl font-bold text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600">
            <img src="/icons/icon-192.png?v=2" alt="" class="h-9 w-9 rounded-xl shadow-sm"> <span class="text-lg max-[374px]:hidden">{{ $brand }}</span>
        </a>

        <nav aria-label="Công cụ miễn phí" class="hidden items-center gap-1 sm:flex">
            @foreach ($tools as $tool)
                @php $isActive = request()->routeIs($tool['active']); @endphp
                <a href="{{ route($tool['route']) }}" @if ($isActive) aria-current="page" @endif
                    class="flex items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-medium transition {{ $isActive ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'text-gray-600 hover:bg-gray-50 hover:text-emerald-700' }}">
                    <span aria-hidden="true">{{ $tool['icon'] }}</span> {{ $tool['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2 text-sm">
            @auth
                <a href="{{ route('net-worth') }}" class="flex items-center gap-1.5 rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white shadow-sm hover:bg-emerald-800">
                    <span aria-hidden="true">📒</span> Vào sổ
                </a>
            @else
                <a href="{{ route('login') }}" class="whitespace-nowrap rounded-full px-3 py-2 font-medium text-gray-700 hover:bg-gray-50 hover:text-emerald-700 sm:border sm:border-gray-200 sm:px-4">Đăng nhập</a>
                <a href="{{ route('register') }}" class="whitespace-nowrap rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white shadow-sm hover:bg-emerald-800">Dùng miễn phí</a>
            @endauth
        </div>
    </div>

    <nav aria-label="Công cụ miễn phí" class="border-t border-gray-100 sm:hidden">
        <div class="mx-auto grid max-w-5xl grid-cols-2 gap-2 px-4 py-2">
            @foreach ($tools as $tool)
                @php $isActive = request()->routeIs($tool['active']); @endphp
                <a href="{{ route($tool['route']) }}" @if ($isActive) aria-current="page" @endif
                    class="flex items-center gap-2 rounded-2xl px-3 py-2 transition {{ $isActive ? 'bg-emerald-50 ring-1 ring-emerald-200' : 'bg-gray-50 active:bg-gray-100' }}">
                    <span class="text-xl" aria-hidden="true">{{ $tool['icon'] }}</span>
                    <span class="min-w-0 leading-tight">
                        <span class="block text-sm font-semibold {{ $isActive ? 'text-emerald-800' : 'text-gray-800' }}">{{ $tool['label'] }}</span>
                        <span class="block truncate text-[11px] text-gray-500">{{ $tool['hint'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </nav>
</header>
