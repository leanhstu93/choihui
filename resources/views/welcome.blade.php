@php
    /** @var array<string, string> $lunarLookup */
    $brand = config('app.name');
    $title = $brand.' · Sổ tay gia đình: ngày giỗ âm lịch, sức khỏe, sửa chữa, vàng, tài sản';
    $description = 'Ứng dụng miễn phí ghi chép việc nhà: ngày giỗ theo âm lịch, sức khỏe cả nhà, sửa chữa đồ dùng, vàng và tổng tài sản. Tự tính, tự nhắc qua email.';

    // Các cuốn sổ trong phần "Mỗi việc một cuốn sổ": khóa tab, tên, ba điểm chính.
    $books = [
        'ngay' => ['🕯️', 'Ngày nhớ', [['🌙', 'Ngày âm tự đổi sang dương'], ['🔢', 'Tự đếm giỗ lần thứ mấy, tròn mấy tuổi'], ['📧', 'Nhắc trước, bạn chọn số ngày']]],
        'suc' => ['🩺', 'Sức khỏe', [['👨‍👩‍👧', 'Sổ riêng cho từng người'], ['📷', 'Giữ ảnh đơn thuốc, kết quả'], ['🔔', 'Nhắc ngày tái khám']]],
        'sua' => ['🔧', 'Sửa chữa', [['🛵', 'Xe, điện thoại, laptop...'], ['⏱️', 'Món thay dùng được bao lâu'], ['📷', 'Giữ ảnh hóa đơn, hạn bảo hành']]],
        'vang' => ['🪙', 'Vàng', [['⚖️', 'Ghi theo chỉ, từng món'], ['💹', 'Nhập giá tiệm, biết lời lỗ'], ['🧾', 'Bán một phần, vốn vẫn khớp']]],
        'hui' => ['🤝', 'Hụi', [['✅', 'Kỳ này đóng bao nhiêu'], ['📈', 'Hốt kỳ nào thì lời'], ['👥', 'Chủ hụi: biết ngay ai chưa đóng']]],
    ];

    // Ngày âm hay tra: tên, ngày, tháng.
    $lunarPresets = [['Rằm tháng Giêng', 15, 1], ['Giỗ Tổ Hùng Vương', 10, 3], ['Vu Lan', 15, 7], ['Ông Công Ông Táo', 23, 12]];
@endphp
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => null])
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ url('/') }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $brand }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ asset('images/og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <style>
            @keyframes float { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
            @keyframes grow { from { transform: scaleY(0) } to { transform: scaleY(1) } }
            .animate-float { animation: float 6s ease-in-out infinite }
            .animate-grow { animation: grow .8s ease-out both; transform-origin: bottom }
            @media (prefers-reduced-motion: reduce) { .animate-float, .animate-grow { animation: none } }
        </style>
    </head>
    <body class="bg-white font-sans text-gray-900 antialiased">
        {{-- Thanh trên --}}
        <header class="sticky top-0 z-30 border-b border-emerald-900/10 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-emerald-800">
                    <img src="/icons/icon-192.png" alt="" class="h-8 w-8 rounded-lg"> {{ $brand }}
                </a>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('login') }}" class="font-medium text-gray-600 hover:text-emerald-700">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Dùng miễn phí</a>
                </div>
            </div>
        </header>

        <main>
            {{-- 1. Hook: một con số tài sản và việc sắp tới --}}
            <section class="relative overflow-hidden bg-gradient-to-b from-emerald-50 via-white to-white">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-200/50 blur-3xl"></div>
                <div class="pointer-events-none absolute -left-24 top-1/2 h-72 w-72 rounded-full bg-amber-200/40 blur-3xl"></div>

                <div class="relative mx-auto grid max-w-5xl items-center gap-10 px-4 pb-16 pt-10 md:grid-cols-2 md:pt-16">
                    <div class="text-center md:text-left">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">✓ Miễn phí · Không cần cài đặt</span>
                        <h1 class="mt-4 text-3xl font-bold leading-tight tracking-tight sm:text-5xl md:text-4xl lg:text-[2.6rem]">
                            Chuyện tiền, chuyện nhà.<br>
                            <span class="text-emerald-700">Gọn trong một cuốn sổ.</span>
                        </h1>
                        <p class="mt-4 text-lg text-gray-600">Ngày giỗ, sức khỏe cả nhà, đồ sửa, vàng, tài sản. App tự tính, tự đổi lịch âm, nhắc trước qua email.</p>
                        <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-center md:justify-start">
                            <a href="{{ route('register') }}" class="rounded-full bg-emerald-700 px-7 py-3.5 text-center font-semibold text-white shadow-lg shadow-emerald-700/30 transition hover:-translate-y-0.5 hover:bg-emerald-800">Mở sổ miễn phí</a>
                            <a href="#cac-so" class="rounded-full border border-emerald-700 px-7 py-3.5 text-center font-semibold text-emerald-700 hover:bg-emerald-50">Xem có gì →</a>
                        </div>
                    </div>

                    <div class="animate-float">
                        <x-landing.phone label="Sổ tay: hôm nay theo âm lịch, ngày giỗ, lịch tái khám và tài sản ròng">
                            <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                                <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-emerald-50 text-emerald-800">
                                    <span class="text-[9px] font-semibold">TH11</span><span class="font-bold leading-none">12</span>
                                </div>
                                <div><div class="text-gray-500">Hôm nay · Thứ Năm</div><div class="font-semibold">12/11/2026 · 4/10 ÂL</div></div>
                            </div>
                            <div class="rounded-xl bg-amber-50 p-3 text-amber-900">
                                <div class="font-semibold">🕯️ Giỗ ông nội · 15/7 âm</div>
                                <div class="mt-0.5">Giỗ lần thứ 12 · còn 3 ngày</div>
                            </div>
                            <div class="rounded-xl bg-sky-50 p-3 text-sky-900">
                                <div class="font-semibold">🩺 Mẹ tái khám tim mạch</div>
                                <div class="mt-0.5">Thứ Sáu tuần này</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="font-semibold">🛵 Xe Air Blade · thay nhớt</div>
                                <div class="mt-0.5 text-gray-500">Lần trước cách đây 3 tháng</div>
                            </div>
                            <div class="rounded-xl bg-emerald-700 p-3 text-white">
                                <div class="text-emerald-100">Tài sản ròng</div>
                                <div class="text-xl font-bold">186.400.000 đ</div>
                                <div class="mt-2 flex h-14 items-end gap-1.5">
                                    @foreach ([52, 58, 61, 70, 76, 84] as $index => $height)
                                        <div class="animate-grow flex-1 rounded-t {{ $loop->last ? 'bg-amber-300' : 'bg-white/40' }}" style="height: {{ $height }}%; animation-delay: {{ $index * 80 }}ms"></div>
                                    @endforeach
                                </div>
                                <div class="mt-1 flex justify-between text-[10px] text-emerald-100"><span>Th6</span><span>Th11</span></div>
                            </div>
                        </x-landing.phone>
                    </div>
                </div>
            </section>

            {{-- 2. Lợi ích: icon lớn, ít chữ --}}
            <section class="mx-auto grid max-w-5xl grid-cols-2 gap-3 px-4 md:grid-cols-4">
                @foreach ([
                    ['🧮', 'Tự tính', 'Lời lỗ, nợ, độ bền'],
                    ['🌙', 'Hiểu lịch âm', 'Giỗ, rằm, Tết'],
                    ['🔔', 'Nhắc trước hạn', 'Qua email'],
                    ['🔒', 'Chỉ mình bạn xem', 'Dữ liệu riêng tư'],
                ] as [$icon, $heading, $sub])
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <div class="text-3xl" aria-hidden="true">{{ $icon }}</div>
                        <div class="mt-2 font-semibold">{{ $heading }}</div>
                        <div class="text-sm text-gray-500">{{ $sub }}</div>
                    </div>
                @endforeach
            </section>

            {{-- 3. Các cuốn sổ: chuyển tab giữa các màn hình --}}
            <section id="cac-so" class="mx-auto max-w-5xl scroll-mt-16 px-4 py-16" x-data="{ book: 'ngay' }">
                <h2 class="text-center text-3xl font-bold">Mỗi việc một cuốn sổ</h2>
                <p class="mt-2 text-center text-gray-600">Dùng cuốn nào mở cuốn đó. Tất cả gộp về trang Tổng.</p>
                <div class="-mx-4 mt-6 overflow-x-auto px-4">
                    <div class="mx-auto flex w-fit gap-1 rounded-full bg-gray-100 p-1 text-sm font-semibold" role="tablist">
                        @foreach ($books as $key => [$icon, $name])
                            <button type="button" role="tab" @click="book = @js($key)" :aria-selected="book === @js($key)" :class="book === @js($key) ? 'bg-emerald-700 text-white shadow' : 'text-gray-600'" class="whitespace-nowrap rounded-full px-4 py-2 transition">
                                <span aria-hidden="true">{{ $icon }}</span> {{ $name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-10 grid items-center gap-10 md:grid-cols-2">
                    <div>
                        <div x-show="book === 'hui'" x-cloak x-transition.opacity.duration.300ms>
                            <x-landing.phone label="Sổ hụi: chi tiết một dây hụi">
                                <div class="rounded-xl bg-white p-3 shadow-sm">
                                    <div class="font-semibold">Hụi 1tr cô Hoa</div>
                                    <div class="mt-2 grid grid-cols-3 gap-1 text-center">
                                        <div><div class="text-gray-500">Đã đóng</div><div class="font-bold">4.500.000</div></div>
                                        <div><div class="text-gray-500">Đã nhận</div><div class="font-bold">0</div></div>
                                        <div><div class="text-gray-500">Nếu hốt kỳ 7</div><div class="font-bold text-emerald-700">+100.000</div></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-1">
                                    @foreach (range(1, 12) as $roundNo)
                                        <div @class(['rounded-lg border p-1.5', 'border-emerald-300 bg-emerald-50 text-emerald-800' => $roundNo <= 5, 'border-orange-300 bg-orange-50 text-orange-800' => $roundNo === 6, 'border-gray-200 bg-white text-gray-500' => $roundNo > 6])>
                                            <div class="font-semibold">Kỳ {{ $roundNo }}</div>
                                            <div>{{ $roundNo <= 5 ? 'Đã đóng' : ($roundNo === 6 ? 'Cần đóng' : 'Chưa mở') }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-landing.phone>
                        </div>

                        <div x-show="book === 'vang'" x-cloak x-transition.opacity.duration.300ms>
                            <x-landing.phone label="Sổ vàng: các món đang giữ và lời lỗ">
                                <div class="rounded-xl bg-white p-3 shadow-sm">
                                    <div class="text-gray-500">Đang giữ 12,5 chỉ · trị giá</div>
                                    <div class="text-lg font-bold">142.500.000 đ</div>
                                    <div class="font-semibold text-emerald-700">Lời tạm tính +18.300.000 đ</div>
                                </div>
                                <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                                    @foreach ([['Nhẫn trơn 9999', '5 chỉ', '60.000.000', '+7.200.000'], ['Kiềng cưới 24K', '4 chỉ', '48.000.000', '+6.500.000'], ['Dây chuyền 18K', '3,5 chỉ', '34.500.000', '+4.600.000']] as [$name, $weight, $value, $gain])
                                        <li class="flex items-center justify-between gap-2 px-3 py-2">
                                            <div><div class="font-medium">{{ $name }}</div><div class="text-gray-500">{{ $weight }}</div></div>
                                            <div class="text-right"><div class="font-semibold">{{ $value }}</div><div class="text-emerald-700">{{ $gain }}</div></div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="rounded-xl bg-amber-50 p-2 text-amber-900">Giá tiệm cập nhật hôm nay</div>
                            </x-landing.phone>
                        </div>

                        <div x-show="book === 'sua'" x-cloak x-transition.opacity.duration.300ms>
                            <x-landing.phone label="Sổ sửa chữa: các lần sửa một chiếc xe">
                                <div class="rounded-xl bg-white p-3 shadow-sm">
                                    <div class="font-semibold">🛵 Xe Air Blade</div>
                                    <div class="text-gray-500">8 lần sửa · tổng 3.240.000 đ</div>
                                </div>
                                <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                                    @foreach ([['Thay lốp sau', '450.000 đ', 'Lốp trước dùng được 1 năm 8 tháng', 'text-emerald-700'], ['Thay bình ắc quy', '650.000 đ', 'Bảo hành đến 03/2027', 'text-sky-700'], ['Thay nhớt', '120.000 đ', '24.350 km', 'text-gray-500']] as [$part, $cost, $note, $color])
                                        <li class="px-3 py-2">
                                            <div class="flex justify-between"><span class="font-medium">{{ $part }}</span><span class="font-semibold">{{ $cost }}</span></div>
                                            <div class="{{ $color }}">{{ $note }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="rounded-xl bg-gray-50 p-2 text-gray-600">📷 2 ảnh hóa đơn</div>
                            </x-landing.phone>
                        </div>

                        <div x-show="book === 'ngay'" x-transition.opacity.duration.300ms>
                            <x-landing.phone label="Ngày nhớ: giỗ, sinh nhật, kỷ niệm sắp tới">
                                <ul class="space-y-2">
                                    @foreach ([['🕯️', 'Giỗ ông nội', '15/7 âm · Giỗ lần thứ 12', 'còn 3 ngày', 'bg-amber-100 text-amber-800'], ['🎂', 'Sinh nhật bé Na', '20/11 · Tròn 6 tuổi', 'còn 8 ngày', 'bg-emerald-100 text-emerald-800'], ['💍', 'Kỷ niệm ngày cưới', '10/12 · 10 năm', 'còn 28 ngày', 'bg-gray-100 text-gray-600'], ['🕯️', 'Giỗ bà ngoại', '8/11 âm · Giỗ lần thứ 5', 'còn 35 ngày', 'bg-gray-100 text-gray-600']] as [$icon, $name, $detail, $left, $badge])
                                        <li class="flex items-center justify-between gap-2 rounded-xl bg-white p-3 shadow-sm">
                                            <div><div class="font-semibold"><span aria-hidden="true">{{ $icon }}</span> {{ $name }}</div><div class="text-gray-500">{{ $detail }}</div></div>
                                            <span class="whitespace-nowrap rounded-full px-2 py-1 font-semibold {{ $badge }}">{{ $left }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-landing.phone>
                        </div>

                        <div x-show="book === 'suc'" x-cloak x-transition.opacity.duration.300ms>
                            <x-landing.phone label="Sổ sức khỏe: các lần khám của một người">
                                <div class="rounded-xl bg-white p-3 shadow-sm">
                                    <div class="font-semibold">👩 Mẹ · sinh 1962</div>
                                    <div class="mt-1 rounded-lg bg-sky-50 p-2 text-sky-900">Hẹn tái khám tim mạch: <strong>thứ Sáu tuần này</strong></div>
                                </div>
                                <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                                    @foreach ([['Khám bệnh', 'Tim mạch · BV Chợ Rẫy', '📷 3 ảnh'], ['Xét nghiệm', 'Mỡ máu, đường huyết', '📷 2 ảnh'], ['Tiêm chủng', 'Cúm mùa', '']] as [$kind, $detail, $photos])
                                        <li class="px-3 py-2">
                                            <div class="flex justify-between"><span class="font-medium">{{ $kind }}</span><span class="text-gray-500">{{ $photos }}</span></div>
                                            <div class="text-gray-500">{{ $detail }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-landing.phone>
                        </div>
                    </div>

                    <div>
                        @foreach ($books as $key => [$icon, $name, $points])
                            <ul class="space-y-4 text-lg" x-show="book === @js($key)" @if (! $loop->first) x-cloak @endif>
                                @foreach ($points as [$pointIcon, $point])
                                    <li class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4">
                                        <span class="text-2xl" aria-hidden="true">{{ $pointIcon }}</span>
                                        <span class="font-semibold">{{ $point }}</span>
                                    </li>
                                @endforeach
                                <li><a href="{{ route('register') }}" class="inline-block rounded-full bg-emerald-700 px-6 py-3 font-semibold text-white hover:bg-emerald-800">Mở sổ {{ mb_strtolower($name) }} →</a></li>
                            </ul>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- 4. Thử tra: ngày âm năm nay rơi vào ngày dương nào --}}
            <section id="tra-ngay-am" class="bg-gray-900 py-16 text-white" x-data="landingLunar(@js($lunarLookup), @js(today()->toDateString()))">
                <div class="mx-auto max-w-3xl px-4">
                    <h2 class="text-center text-3xl font-bold">Ngày giỗ năm nay rơi vào ngày nào?</h2>
                    <p class="mt-2 text-center text-gray-400">Chọn ngày âm, xem ngay ngày dương sắp tới.</p>

                    <div class="mt-8 flex flex-wrap justify-center gap-2">
                        @foreach ($lunarPresets as [$presetName, $presetDay, $presetMonth])
                            <button type="button" @click="day = {{ $presetDay }}; month = {{ $presetMonth }}" :class="day === {{ $presetDay }} && month === {{ $presetMonth }} ? 'bg-emerald-500 text-gray-900' : 'bg-white/10 hover:bg-white/20'" class="rounded-full px-4 py-2 text-sm font-semibold transition">{{ $presetName }}</button>
                        @endforeach
                    </div>

                    <div class="mt-8 grid items-center gap-6 sm:grid-cols-2">
                        <div class="flex items-end justify-center gap-3">
                            <label class="block">
                                <span class="text-sm text-gray-400">Ngày âm</span>
                                <select x-model.number="day" class="mt-1 block w-24 rounded-xl border-0 bg-white/10 text-lg font-semibold text-white focus:ring-2 focus:ring-emerald-500">
                                    @foreach (range(1, 30) as $optionDay)
                                        <option value="{{ $optionDay }}" class="text-gray-900">{{ $optionDay }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <span class="pb-2 text-2xl text-gray-500">/</span>
                            <label class="block">
                                <span class="text-sm text-gray-400">Tháng âm</span>
                                <select x-model.number="month" class="mt-1 block w-24 rounded-xl border-0 bg-white/10 text-lg font-semibold text-white focus:ring-2 focus:ring-emerald-500">
                                    @foreach (range(1, 12) as $optionMonth)
                                        <option value="{{ $optionMonth }}" class="text-gray-900">{{ $optionMonth }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="rounded-2xl bg-white/5 p-5 text-center sm:text-left" aria-live="polite">
                            <div class="text-sm text-gray-400">Lần tới là</div>
                            <div class="text-2xl font-bold" x-text="weekday"></div>
                            <div class="text-3xl font-bold text-emerald-400" x-text="solar"></div>
                            <div class="mt-1 font-semibold text-amber-300" x-text="countdown"></div>
                        </div>
                    </div>

                    <a href="{{ route('register') }}" class="mx-auto mt-8 block max-w-sm rounded-full bg-emerald-500 py-3 text-center font-semibold text-gray-900 hover:bg-emerald-400">Lưu ngày này vào sổ, app nhắc trước →</a>
                    <p class="mt-6 text-center text-xs text-gray-500">Theo giờ Việt Nam. Năm có tháng nhuận vẫn lấy tháng thường; ngày 30 gặp tháng thiếu tính ngày 29.</p>
                </div>
            </section>

            {{-- 5. Ba bước --}}
            <section class="mx-auto max-w-5xl px-4 py-16">
                <h2 class="text-center text-3xl font-bold">3 bước là xong</h2>
                <ol class="mt-10 grid gap-4 md:grid-cols-3">
                    @foreach ([['1', '📝', 'Tạo tài khoản', '30 giây, miễn phí'], ['2', '📚', 'Mở cuốn sổ cần dùng', 'Ngày nhớ, sức khỏe, vàng...'], ['3', '🔔', 'Ghi một lần', 'App tự tính, tự nhắc']] as [$number, $icon, $heading, $sub])
                        <li class="relative rounded-3xl bg-emerald-50 p-6 text-center">
                            <span class="absolute left-4 top-4 flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white">{{ $number }}</span>
                            <div class="text-5xl" aria-hidden="true">{{ $icon }}</div>
                            <div class="mt-3 text-lg font-semibold">{{ $heading }}</div>
                            <div class="text-gray-600">{{ $sub }}</div>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- 6. Câu hỏi thường gặp --}}
            <section class="mx-auto max-w-2xl px-4 pb-16">
                <h2 class="text-center text-3xl font-bold">Hỏi nhanh</h2>
                <div class="mt-8 space-y-3">
                    @foreach ([
                        ['Có mất phí không?', 'Không. Dùng miễn phí.'],
                        ['Ai xem được sổ của tôi?', 'Chỉ bạn. Mỗi tài khoản chỉ thấy dữ liệu của chính mình.'],
                        ['Có phải cài app không?', 'Không. Mở trên trình duyệt điện thoại, bấm "Thêm vào màn hình chính" là dùng như app.'],
                        ['Ngày giỗ theo âm lịch có tính đúng không?', 'Có. App đổi ngày âm sang dương theo giờ Việt Nam mỗi năm. Năm có tháng nhuận vẫn lấy tháng thường, đúng tục cúng giỗ.'],
                        ['Tôi chỉ cần ghi ngày giỗ thôi được không?', 'Được. Mỗi việc một cuốn sổ riêng, cần cuốn nào dùng cuốn đó, không phải điền hết.'],
                    ] as [$question, $answer])
                        <details class="group rounded-2xl bg-gray-50 p-4 [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex cursor-pointer items-center justify-between font-semibold">
                                {{ $question }}
                                <span class="text-emerald-700 transition group-open:rotate-45" aria-hidden="true">+</span>
                            </summary>
                            <p class="mt-2 text-gray-600">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- 7. Kêu gọi cuối trang --}}
            <section class="bg-gradient-to-br from-emerald-700 to-emerald-900 px-4 py-16 text-center text-white">
                <h2 class="text-3xl font-bold">Bỏ sổ giấy. Dùng {{ $brand }}.</h2>
                <p class="mt-2 text-emerald-100">Tạo tài khoản 30 giây. Miễn phí.</p>
                <a href="{{ route('register') }}" class="mt-6 inline-block rounded-full bg-white px-8 py-3.5 font-semibold text-emerald-800 shadow-lg transition hover:-translate-y-0.5">Mở sổ ngay</a>
            </section>
        </main>

        <footer class="px-4 py-6 text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ $brand }} · Công cụ ghi chép cá nhân, không nhận giữ tiền.
            · <a href="{{ route('privacy') }}" class="underline hover:text-gray-600">Chính sách bảo mật</a>
        </footer>

        <script>
            document.addEventListener('alpine:init', () => {
                // Tra ngày âm: bảng "d/m" → Y-m-d tính sẵn trên server bằng App\Queries\LunarDateLookup.
                window.Alpine.data('landingLunar', (table, today) => ({
                    table,
                    day: 15,
                    month: 7,
                    weekdays: ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'],
                    get date() {
                        return this.parse(this.table[`${this.day}/${this.month}`]);
                    },
                    get weekday() {
                        return this.weekdays[this.date.getUTCDay()];
                    },
                    get solar() {
                        const date = this.date;

                        return `${String(date.getUTCDate()).padStart(2, '0')}/${String(date.getUTCMonth() + 1).padStart(2, '0')}/${date.getUTCFullYear()}`;
                    },
                    get countdown() {
                        const days = Math.round((this.date - this.parse(today)) / 86400000);

                        return days === 0 ? 'Hôm nay' : `Còn ${days} ngày`;
                    },
                    parse(isoDate) {
                        const [year, month, day] = isoDate.split('-').map(Number);

                        return new Date(Date.UTC(year, month - 1, day));
                    },
                }));
            });
        </script>
        @livewireScripts
        <x-cookie-consent />
    </body>
</html>
