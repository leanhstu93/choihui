@php
    $title = 'Sổ hụi · Ghi sổ hụi trên điện thoại, tự tính tiền đóng, tiền hốt';
    $description = 'Ứng dụng miễn phí cho chủ hụi và hụi viên: biết ngay ai chưa đóng, thu 1 chạm, tự tính tiền hốt, lời lỗ từng kỳ, nhắc hạn qua email.';
    // Dây 7 tay, M = 1.000.000, kỳ 4 lãi 100.000, tay 4 hốt: tay 1–3 đã hốt đóng M, tay 5–7 đóng M − L.
    $members = [['Chị Lan', 1, 1000000], ['Anh Tư', 2, 1000000], ['Cô Ba', 3, 1000000], ['Chị Mai', 5, 900000], ['Chú Sáu', 6, 900000], ['Bé Na', 7, 900000]];
@endphp
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => null])
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ url('/') }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Sổ hụi">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ asset('images/og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <style>
            @keyframes float { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
            @keyframes pop { 0% { transform: scale(.6); opacity: 0 } 60% { transform: scale(1.15) } 100% { transform: scale(1); opacity: 1 } }
            .animate-float { animation: float 6s ease-in-out infinite }
            .animate-pop { animation: pop .35s ease-out both }
            @media (prefers-reduced-motion: reduce) { .animate-float, .animate-pop { animation: none } }
        </style>
    </head>
    <body class="bg-white font-sans text-gray-900 antialiased">
        {{-- Thanh trên --}}
        <header class="sticky top-0 z-30 border-b border-emerald-900/10 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold text-emerald-800">
                    <img src="/icons/icon-192.png" alt="" class="h-8 w-8 rounded-lg"> Sổ hụi
                </a>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('login') }}" class="font-medium text-gray-600 hover:text-emerald-700">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Dùng miễn phí</a>
                </div>
            </div>
        </header>

        <main>
            {{-- 1. Hook: điện thoại đang thu tiền --}}
            <section class="relative overflow-hidden bg-gradient-to-b from-emerald-50 via-white to-white">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-200/50 blur-3xl"></div>
                <div class="pointer-events-none absolute -left-24 top-1/2 h-72 w-72 rounded-full bg-amber-200/40 blur-3xl"></div>

                <div class="relative mx-auto grid max-w-5xl items-center gap-10 px-4 pb-16 pt-10 md:grid-cols-2 md:pt-16">
                    <div class="text-center md:text-left">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">✓ Miễn phí · Không cần cài đặt</span>
                        <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                            Sổ hụi trong túi.<br>
                            <span class="text-emerald-700">Ai chưa đóng</span> biết liền.
                        </h1>
                        <p class="mt-4 text-lg text-gray-600">Tự tính tiền đóng, tiền hốt, lời lỗ. Thu tiền 1&nbsp;chạm.</p>
                        <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-center md:justify-start">
                            <a href="{{ route('register') }}" class="rounded-full bg-emerald-700 px-7 py-3.5 text-center font-semibold text-white shadow-lg shadow-emerald-700/30 transition hover:-translate-y-0.5 hover:bg-emerald-800">Tạo sổ hụi miễn phí</a>
                            <a href="#tinh-thu" class="rounded-full border border-emerald-700 px-7 py-3.5 text-center font-semibold text-emerald-700 hover:bg-emerald-50">Thử tính hụi →</a>
                        </div>
                    </div>

                    <div x-data="landingCollect(@js($members))" x-init="start()" class="animate-float">
                        <x-landing.phone label="Màn hình thu tiền một kỳ hụi">
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <div class="font-semibold">Hụi 1tr nhà mình · Kỳ 4</div>
                                <div class="mt-2 flex items-end justify-between">
                                    <div>
                                        <div class="text-gray-500">Đã thu</div>
                                        <div class="text-lg font-bold text-emerald-700" x-text="money(collected)"></div>
                                    </div>
                                    <div class="text-right text-gray-500"><span x-text="paid"></span>/<span x-text="rows.length"></span> tay</div>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" :style="`width: ${paid / rows.length * 100}%`"></div>
                                </div>
                            </div>
                            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                                <template x-for="(row, index) in rows" :key="index">
                                    <li class="flex items-center justify-between gap-2 px-3 py-2">
                                        <div>
                                            <div class="font-medium" x-text="row[0]"></div>
                                            <div class="text-gray-500">Tay <span x-text="row[1]"></span> · <span x-text="money(row[2])"></span></div>
                                        </div>
                                        <span x-show="index < paid" class="animate-pop rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-700">✓ Đã thu</span>
                                        <span x-show="index >= paid" class="rounded-lg bg-emerald-700 px-2 py-1 font-semibold text-white" :class="index === paid && 'ring-4 ring-emerald-300'">Đã thu</span>
                                    </li>
                                </template>
                            </ul>
                            <div class="rounded-xl bg-sky-50 p-2 text-sky-800">Chi cho Tay 4: <strong>5.700.000 đ</strong></div>
                        </x-landing.phone>
                    </div>
                </div>
            </section>

            {{-- 2. Lợi ích: icon lớn, ít chữ --}}
            <section class="mx-auto grid max-w-5xl grid-cols-2 gap-3 px-4 md:grid-cols-4">
                @foreach ([
                    ['👆', 'Thu 1 chạm', 'Không ghi sổ tay'],
                    ['🧮', 'Tự tính tiền', 'Sống, chết, thảo'],
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

            {{-- 3. Bạn là ai: chuyển giữa hai màn hình --}}
            <section class="mx-auto max-w-5xl px-4 py-16" x-data="{ role: 'chu' }">
                <h2 class="text-center text-3xl font-bold">Bạn là ai?</h2>
                <div class="mx-auto mt-6 flex w-fit rounded-full bg-gray-100 p-1 text-sm font-semibold" role="tablist">
                    <button type="button" role="tab" @click="role = 'chu'" :aria-selected="role === 'chu'" :class="role === 'chu' ? 'bg-emerald-700 text-white shadow' : 'text-gray-600'" class="rounded-full px-5 py-2 transition">Chủ hụi</button>
                    <button type="button" role="tab" @click="role = 'vien'" :aria-selected="role === 'vien'" :class="role === 'vien' ? 'bg-emerald-700 text-white shadow' : 'text-gray-600'" class="rounded-full px-5 py-2 transition">Hụi viên</button>
                </div>

                <div class="mt-10 grid items-center gap-10 md:grid-cols-2">
                    <div x-show="role === 'chu'" x-transition.opacity.duration.300ms>
                        <x-landing.phone label="Trang chủ của chủ hụi">
                            <div class="rounded-xl bg-emerald-700 p-3 text-white">
                                <div class="text-emerald-100">Còn phải thu</div>
                                <div class="text-xl font-bold">3.600.000 đ</div>
                                <div class="mt-1 font-semibold text-amber-200">1 kỳ đã quá hạn</div>
                            </div>
                            <div class="rounded-xl bg-white shadow-sm">
                                <div class="flex justify-between p-2"><span class="font-semibold">Hụi 2tr chợ Bà Chiểu · Kỳ 6</span></div>
                                @foreach ([['Chị Hồng', 'Tay 3', '1.800.000 đ'], ['Anh Phúc', 'Tay 8', '1.800.000 đ']] as [$name, $slot, $amount])
                                    <div class="flex items-center justify-between border-t border-gray-100 px-2 py-2">
                                        <div><div class="font-medium">{{ $name }}</div><div class="text-gray-500">{{ $slot }}</div></div>
                                        <div class="flex items-center gap-1"><span class="font-semibold">{{ $amount }}</span><span class="rounded-lg bg-emerald-700 px-2 py-1 font-semibold text-white">Đã thu</span></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="rounded-xl bg-white p-2 shadow-sm"><div class="text-gray-500">Thảo đã nhận</div><div class="font-bold text-emerald-700">1.000.000 đ</div></div>
                                <div class="rounded-xl bg-white p-2 shadow-sm"><div class="text-gray-500">Hụi viên nợ</div><div class="font-bold text-red-600">900.000 đ</div></div>
                            </div>
                        </x-landing.phone>
                    </div>
                    <div x-show="role === 'vien'" x-cloak x-transition.opacity.duration.300ms>
                        <x-landing.phone label="Chi tiết dây hụi của hụi viên">
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

                    <ul class="space-y-4 text-lg">
                        <template x-for="item in (role === 'chu'
                            ? [['✅', 'Biết ngay ai chưa đóng'], ['💸', 'Tự tính tiền chi, tiền bù'], ['📒', 'Nợ từng hụi viên, rõ ràng']]
                            : [['📅', 'Kỳ này đóng bao nhiêu'], ['📈', 'Hốt kỳ nào thì lời'], ['🔔', 'Nhắc trước ngày đóng']])" :key="item[1]">
                            <li class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4">
                                <span class="text-2xl" x-text="item[0]" aria-hidden="true"></span>
                                <span class="font-semibold" x-text="item[1]"></span>
                            </li>
                        </template>
                        <li><a href="{{ route('register') }}" class="inline-block rounded-full bg-emerald-700 px-6 py-3 font-semibold text-white hover:bg-emerald-800" x-text="role === 'chu' ? 'Quản lý dây đầu tiên →' : 'Ghi dây đầu tiên →'">Bắt đầu →</a></li>
                    </ul>
                </div>
            </section>

            {{-- 4. Thử tính: biểu đồ lời / lỗ theo kỳ hốt --}}
            <section id="tinh-thu" class="bg-gray-900 py-16 text-white" x-data="landingCalculator()">
                <div class="mx-auto max-w-5xl px-4">
                    <h2 class="text-center text-3xl font-bold">Hốt kỳ nào thì lời?</h2>
                    <p class="mt-2 text-center text-gray-400">Kéo thử. Bấm vào cột để chọn kỳ hốt.</p>

                    <div class="mt-8 grid gap-8 md:grid-cols-5">
                        <div class="space-y-5 md:col-span-2">
                            <div>
                                <div class="text-sm text-gray-400">Mệnh giá</div>
                                <div class="mt-2 flex gap-2">
                                    <template x-for="value in [1000000, 2000000, 5000000]" :key="value">
                                        <button type="button" @click="setFace(value)" :class="face === value ? 'bg-emerald-500 text-gray-900' : 'bg-white/10'" class="flex-1 rounded-full py-2 text-sm font-semibold transition" x-text="short(value)"></button>
                                    </template>
                                </div>
                            </div>
                            <label class="block">
                                <span class="flex justify-between text-sm text-gray-400">Số tay <strong class="text-white" x-text="slots"></strong></span>
                                <input type="range" min="5" max="30" x-model.number="slots" @input="k = Math.min(k, slots)" class="mt-2 w-full accent-emerald-500">
                            </label>
                            <label class="block">
                                <span class="flex justify-between text-sm text-gray-400">Lãi thăm mỗi kỳ <strong class="text-white" x-text="money(bid)"></strong></span>
                                <input type="range" min="0" :max="face * 0.3" :step="face / 100" x-model.number="bid" class="mt-2 w-full accent-emerald-500">
                            </label>
                            <div class="rounded-2xl bg-white/5 p-4">
                                <div class="text-sm text-gray-400">Hốt kỳ <span x-text="k"></span> nhận</div>
                                <div class="text-3xl font-bold" x-text="money(payout(k))"></div>
                                <div class="mt-1 text-lg font-semibold" :class="profit(k) >= 0 ? 'text-emerald-400' : 'text-red-400'" x-text="(profit(k) >= 0 ? 'Lời ' : 'Lỗ ') + money(Math.abs(profit(k)))"></div>
                            </div>
                        </div>

                        <div class="md:col-span-3">
                            <div class="flex h-64 items-center gap-1" role="img" :aria-label="`Lời lỗ theo kỳ hốt của dây ${slots} tay`">
                                <template x-for="round in rounds()" :key="round">
                                    <button type="button" @click="k = round" class="group relative flex h-full flex-1 flex-col" :aria-label="`Kỳ ${round}`">
                                        <div class="flex flex-1 items-end">
                                            <div class="w-full rounded-t transition-all duration-300" :class="[profit(round) > 0 ? 'bg-emerald-500' : 'bg-transparent', round === k && 'ring-2 ring-white']" :style="`height: ${profit(round) > 0 ? barHeight(round) : 0}%`"></div>
                                        </div>
                                        <div class="h-px w-full bg-white/30"></div>
                                        <div class="flex flex-1 items-start">
                                            <div class="w-full rounded-b transition-all duration-300" :class="[profit(round) < 0 ? 'bg-red-500' : 'bg-transparent', round === k && 'ring-2 ring-white']" :style="`height: ${profit(round) < 0 ? barHeight(round) : 0}%`"></div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <div class="mt-2 flex justify-between text-xs text-gray-400"><span>Kỳ 1 · hốt sớm</span><span>hốt muộn · Kỳ <span x-text="slots"></span></span></div>
                            <a href="{{ route('register') }}" class="mt-6 block rounded-full bg-emerald-500 py-3 text-center font-semibold text-gray-900 hover:bg-emerald-400">Lưu dây này vào sổ, miễn phí →</a>
                        </div>
                    </div>
                    <p class="mt-6 text-center text-xs text-gray-500">Minh họa: mọi kỳ cùng lãi thăm, chưa trừ thảo. Trong app, mỗi kỳ dùng đúng lãi thăm bạn nhập.</p>
                </div>
            </section>

            {{-- 5. Ba bước --}}
            <section class="mx-auto max-w-5xl px-4 py-16">
                <h2 class="text-center text-3xl font-bold">3 bước là xong</h2>
                <ol class="mt-10 grid gap-4 md:grid-cols-3">
                    @foreach ([['1', '📝', 'Tạo dây', 'Mệnh giá, số tay, ngày mở'], ['2', '🎯', 'Nhập lãi mỗi kỳ', 'App tự tính ai đóng bao nhiêu'], ['3', '👆', 'Bấm "Đã thu"', 'Xong. Hết ghi sổ tay.']] as [$number, $icon, $heading, $sub])
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
                        ['Hụi viên có cần tài khoản không?', 'Không. Chủ hụi tự ghi, rồi gửi ảnh phiếu hoặc tin nhắc qua Zalo.'],
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
                <h2 class="text-3xl font-bold">Bỏ sổ tay. Dùng Sổ hụi.</h2>
                <p class="mt-2 text-emerald-100">Tạo tài khoản 30 giây. Miễn phí.</p>
                <a href="{{ route('register') }}" class="mt-6 inline-block rounded-full bg-white px-8 py-3.5 font-semibold text-emerald-800 shadow-lg transition hover:-translate-y-0.5">Tạo sổ hụi ngay</a>
            </section>
        </main>

        <footer class="px-4 py-6 text-center text-xs text-gray-400">
            © {{ date('Y') }} Sổ hụi · Công cụ ghi sổ và tính toán hụi, không nhận giữ tiền hay tổ chức hụi.
            · <a href="{{ route('privacy') }}" class="underline hover:text-gray-600">Chính sách bảo mật</a>
        </footer>

        <script>
            const formatVnd = (value) => Math.round(value).toLocaleString('vi-VN') + ' đ';

            document.addEventListener('alpine:init', () => {
                // Phần đầu trang: các tay lần lượt được tick "Đã thu", lặp lại.
                window.Alpine.data('landingCollect', (rows) => ({
                    rows,
                    paid: 0,
                    get collected() {
                        return this.rows.slice(0, this.paid).reduce((sum, row) => sum + row[2], 0);
                    },
                    money: formatVnd,
                    start() {
                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                            this.paid = 4;
                            return;
                        }
                        setInterval(() => { this.paid = this.paid >= this.rows.length + 2 ? 0 : this.paid + 1; }, 1100);
                    },
                }));

                // Thử tính: cùng công thức với app (document/01-dac-ta.md mục 5), mọi kỳ cùng lãi thăm L.
                // Nhận = (k − 1)M + (N − k)(M − L); lời / lỗ = L × (2k − N − 1).
                window.Alpine.data('landingCalculator', () => ({
                    face: 1000000,
                    slots: 12,
                    bid: 100000,
                    k: 9,
                    money: formatVnd,
                    short: (value) => (value / 1000000) + ' triệu',
                    setFace(value) {
                        this.bid = Math.round(this.bid / this.face * value);
                        this.face = value;
                    },
                    rounds() {
                        return Array.from({ length: this.slots }, (_, index) => index + 1);
                    },
                    payout(round) {
                        return (round - 1) * this.face + (this.slots - round) * (this.face - this.bid);
                    },
                    profit(round) {
                        return this.bid * (2 * round - this.slots - 1);
                    },
                    barHeight(round) {
                        const max = this.bid * (this.slots - 1);
                        return max === 0 ? 0 : Math.max(4, Math.abs(this.profit(round)) / max * 100);
                    },
                }));
            });
        </script>
        @livewireScripts
        <x-cookie-consent />
    </body>
</html>
