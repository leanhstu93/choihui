@props(['calendar', 'today', 'selected' => null, 'month' => null])

@php
    /** @var array{year: int, months: list<list<array<int, int>>>, yearNames: array<int, string>, holidays: list<array<string, mixed>>} $calendar */
    $todayIso = $today->format('Y-m-d');
    $selectedIso = $selected ?? $todayIso;
    $startMonth = $month ?? (int) substr($selectedIso, 5, 2);
@endphp

{{-- Khối lịch âm dương (App\Queries\YearCalendar): lịch tháng, tờ lịch ngày đang chọn, ngày lễ, đổi ngày. --}}
<div x-data="lunarCalendar({
        initial: @js($calendar),
        dictionary: @js(\App\Domain\LunarDayInfo::dictionary()),
        today: @js($todayIso),
        selected: @js($selectedIso),
        year: {{ $calendar['year'] }},
        month: {{ $startMonth - 1 }},
        dataUrl: @js(url('lich')),
        pageUrl: @js(url('lich-am')),
    })" class="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">

    {{-- Lịch tháng --}}
    <div class="rounded-3xl bg-white p-4 shadow-sm sm:p-6">
        <div class="relative flex items-center justify-between gap-2">
            <button type="button" @click="shift(-1)" :disabled="loading" class="flex h-10 w-10 items-center justify-center rounded-full text-xl text-gray-600 hover:bg-gray-100 disabled:opacity-40" aria-label="Tháng trước">‹</button>
            <button type="button" @click="picker = ! picker; pickerYear = year" class="rounded-xl px-3 py-1 text-center hover:bg-gray-50" :aria-expanded="picker">
                <span class="block text-lg font-bold">Tháng <span x-text="`${month + 1}/${year}`">{{ $startMonth }}/{{ $calendar['year'] }}</span> <span class="text-sm text-gray-400" aria-hidden="true">▾</span></span>
                <span class="block text-xs text-gray-500" x-text="lunarRange"></span>
            </button>
            <button type="button" @click="shift(1)" :disabled="loading" class="flex h-10 w-10 items-center justify-center rounded-full text-xl text-gray-600 hover:bg-gray-100 disabled:opacity-40" aria-label="Tháng sau">›</button>

            {{-- Chọn nhanh tháng, năm --}}
            <div x-show="picker" x-transition.origin.top @click.outside="picker = false" @keydown.escape.window="picker = false" class="absolute inset-x-0 top-12 z-20 mx-auto max-w-xs rounded-2xl bg-white p-4 shadow-xl ring-1 ring-gray-200" style="display: none">
                <div class="flex items-center justify-between">
                    <button type="button" @click="pickerYear = Math.max(minYear, pickerYear - 1)" class="h-9 w-9 rounded-full text-lg hover:bg-gray-100" aria-label="Năm trước">‹</button>
                    <input type="number" x-model.number="pickerYear" :min="minYear" :max="maxYear" class="w-24 rounded-lg border-gray-200 text-center font-bold" aria-label="Năm">
                    <button type="button" @click="pickerYear = Math.min(maxYear, pickerYear + 1)" class="h-9 w-9 rounded-full text-lg hover:bg-gray-100" aria-label="Năm sau">›</button>
                </div>
                <div class="mt-3 grid grid-cols-4 gap-2">
                    <template x-for="m in 12" :key="m">
                        <button type="button" @click="show(pickerYear, m - 1); picker = false" :class="pickerYear === year && m - 1 === month ? 'bg-emerald-700 text-white' : 'bg-gray-50 hover:bg-emerald-50'" class="rounded-xl py-2 text-sm font-semibold" x-text="`Th ${m}`"></button>
                    </template>
                </div>
                <button type="button" @click="goToday(); picker = false" class="mt-3 w-full rounded-full border border-emerald-700 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Về hôm nay</button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-7 text-center text-xs font-semibold text-gray-500">
            @foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $weekdayName)
                <div class="py-1 {{ $weekdayName === 'CN' ? 'text-rose-600' : '' }}">{{ $weekdayName }}</div>
            @endforeach
        </div>
        <div class="grid touch-pan-y grid-cols-7 gap-1 transition-opacity" :class="loading && 'opacity-50'" @touchstart.passive="touchStart($event)" @touchend="touchEnd($event)">
            <template x-for="cell in cells" :key="cell.key">
                <div>
                    <button type="button" x-show="cell.day" @click="select(cell.iso)" :aria-label="cell.label" :aria-pressed="cell.iso === selected"
                        :class="[
                            cell.iso === selected ? 'ring-2 ring-emerald-600' : (cell.iso === today ? 'ring-2 ring-amber-400' : ''),
                            cell.dayOff ? 'bg-rose-100 hover:bg-rose-200' : (cell.makeUp ? 'bg-gray-200 hover:bg-gray-300' : (cell.holiday ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-100')),
                        ]"
                        class="relative flex aspect-square w-full flex-col items-center justify-center rounded-xl transition sm:aspect-[5/4]">
                        <span class="text-base font-semibold sm:text-lg" :class="cell.dayOff || cell.sunday ? 'text-rose-600' : 'text-gray-900'" x-text="cell.day"></span>
                        <span class="text-[10px] leading-none sm:text-xs" :class="cell.lunarDay === 1 || cell.lunarDay === 15 ? 'font-bold text-rose-600' : 'text-gray-400'" x-text="cell.lunarText"></span>
                        <span x-show="cell.term && ! cell.makeUp" class="mt-0.5 hidden max-w-full truncate px-0.5 text-[10px] leading-none text-sky-700 sm:block" x-text="cell.term"></span>
                        <span x-show="cell.makeUp" class="mt-0.5 text-[9px] font-semibold leading-none text-gray-700 sm:text-[10px]">Làm bù</span>
                        <span x-show="cell.holiday" class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full" :class="cell.holidayOff ? 'bg-rose-500' : 'bg-amber-500'"></span>
                        <span x-show="showGoodDays && cell.good" class="absolute left-1 top-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    </button>
                </div>
            </template>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
            <span><span class="mr-1 inline-block h-3 w-3 rounded bg-rose-100 align-middle"></span>Được nghỉ làm</span>
            <span><span class="mr-1 inline-block h-3 w-3 rounded bg-gray-200 align-middle"></span>Đi làm bù</span>
            <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-amber-500"></span>Ngày lễ không nghỉ</span>
            <span>Số nhỏ là ngày âm, <b class="text-rose-600">đỏ</b> là mùng 1 và rằm</span>
            <button type="button" @click="showGoodDays = ! showGoodDays" :aria-pressed="showGoodDays" :class="showGoodDays ? 'bg-emerald-700 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-full px-3 py-1 font-semibold">
                <span class="mr-1 inline-block h-2 w-2 rounded-full" :class="showGoodDays ? 'bg-white' : 'bg-emerald-500'"></span>Ngày hoàng đạo
            </button>
        </div>
        <p class="mt-2 text-xs text-gray-400 sm:hidden">Vuốt sang trái, phải để đổi tháng.</p>
        <a :href="`${pageUrl}/${year}/${month + 1}`" class="mt-3 inline-block text-sm font-medium text-emerald-700 hover:underline">Bảng âm lịch tháng <span x-text="`${month + 1}/${year}`"></span> →</a>
    </div>

    {{-- Tờ lịch của ngày đang chọn --}}
    <article class="h-fit overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-rose-100" aria-live="polite">
        <div class="flex items-center justify-between bg-gradient-to-br from-red-600 to-rose-700 px-5 py-3 text-sm font-semibold uppercase tracking-wide text-white">
            <span x-text="`Tháng ${sel.month} · ${sel.year}`"></span>
            <span x-show="sel.isToday" class="rounded-full bg-white/20 px-2 py-0.5 text-xs normal-case">Hôm nay</span>
        </div>
        <div class="px-5 pt-4 text-center">
            <div class="text-7xl font-bold leading-none text-red-600 sm:text-8xl" x-text="sel.day"></div>
            <div class="mt-2 text-lg font-semibold" x-text="sel.weekday"></div>
            <div class="mt-3 inline-flex flex-wrap items-center justify-center gap-2 rounded-2xl px-4 py-2 text-sm font-semibold"
                :class="{ off: 'bg-rose-100 text-rose-800', makeup: 'bg-gray-200 text-gray-800', weekend: 'bg-sky-50 text-sky-800', work: 'bg-gray-100 text-gray-700' }[sel.work.type]">
                <span aria-hidden="true" x-text="{ off: '🏖️', makeup: '💼', weekend: '🛋️', work: '💼' }[sel.work.type]"></span>
                <span x-text="sel.work.text"></span>
                <span x-show="sel.work.break" class="rounded-full bg-white/70 px-2 py-0.5 text-xs" x-text="sel.work.break?.official ? 'Chính thức' : 'Dự kiến'"></span>
            </div>
        </div>

        <div class="mx-4 mt-4 flex gap-4 rounded-2xl bg-rose-50 p-4">
            <div class="w-20 shrink-0 text-center">
                <div class="text-3xl" aria-hidden="true" x-text="sel.moonIcon"></div>
                <div class="text-3xl font-bold text-gray-900" x-text="sel.lunarDay"></div>
                <div class="text-xs font-semibold text-gray-600" x-text="sel.lunarMonthLabel"></div>
            </div>
            <dl class="min-w-0 space-y-1 text-sm">
                <div><dt class="inline text-gray-500">Ngày</dt> <dd class="inline font-semibold" x-text="sel.dayName"></dd></div>
                <div><dt class="inline text-gray-500">Tháng</dt> <dd class="inline font-semibold" x-text="sel.monthName"></dd></div>
                <div><dt class="inline text-gray-500">Năm</dt> <dd class="inline font-semibold" x-text="sel.yearName"></dd></div>
                <div><dt class="inline text-gray-500">Tiết</dt> <dd class="inline font-semibold" x-text="sel.term"></dd></div>
                <div class="text-gray-600" x-text="sel.moonName"></div>
            </dl>
        </div>

        <div class="space-y-3 px-5 py-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full px-3 py-1 font-semibold" :class="sel.good ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'" x-text="sel.good ? 'Ngày hoàng đạo' : 'Ngày hắc đạo'"></span>
                <span class="text-gray-500" x-text="`Sao ${sel.starName}`"></span>
                <span x-show="sel.special" class="rounded-full bg-rose-100 px-3 py-1 font-semibold text-rose-700" x-text="sel.special"></span>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Giờ hoàng đạo</div>
                <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                    <template x-for="hour in sel.hours" :key="hour.name">
                        <div class="rounded-lg bg-emerald-50 px-2 py-1 text-center text-xs"><b class="block text-emerald-800" x-text="hour.name"></b><span class="text-gray-600" x-text="hour.range"></span></div>
                    </template>
                </div>
            </div>

            <template x-for="holiday in sel.holidays" :key="holiday.name">
                <div class="rounded-2xl p-3" :class="breakOn(holiday.date) ? 'bg-rose-50' : 'bg-amber-50'">
                    <div class="flex items-center gap-2 font-semibold" :class="breakOn(holiday.date) ? 'text-rose-700' : 'text-amber-800'">
                        <span aria-hidden="true" x-text="holiday.icon"></span>
                        <span x-text="holiday.name"></span>
                        <span x-show="breakOn(holiday.date)" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs">Nghỉ làm</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-700" x-text="holiday.about"></p>
                    <a :href="`${dataUrl}/${sel.year}.ics?ngay=${sel.iso}`" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:underline">📅 Lưu vào lịch điện thoại</a>
                </div>
            </template>
        </div>

        <div class="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-3">
            <button type="button" x-show="! sel.isToday" @click="goToday()" class="rounded-full border border-emerald-700 px-3 py-1.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Hôm nay</button>
            <button type="button" @click="share()" class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-200" x-text="copied ? 'Đã chép liên kết ✓' : 'Chia sẻ'"></button>
            <a :href="sel.url" class="ml-auto self-center text-sm font-semibold text-emerald-700 hover:underline">Chi tiết ngày →</a>
        </div>
    </article>

    {{-- Ngày lễ --}}
    <div class="rounded-3xl bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="whitespace-nowrap font-bold">Ngày lễ</h3>
            <div class="flex gap-1 rounded-full bg-gray-100 p-1 text-xs font-semibold">
                <template x-for="[key, label] in [['next', 'Sắp tới'], ['month', `Tháng ${month + 1}`], ['year', `Năm ${year}`], ['breaks', 'Lịch nghỉ']]" :key="key">
                    <button type="button" @click="listMode = key" :class="listMode === key ? 'bg-white shadow' : 'text-gray-500'" class="whitespace-nowrap rounded-full px-2.5 py-1" x-text="label"></button>
                </template>
            </div>
        </div>
        {{-- Các đợt nghỉ lễ của năm --}}
        <div x-show="listMode === 'breaks'" class="mt-3" style="display: none">
            <ul class="divide-y divide-gray-100">
                <template x-for="item in breakList" :key="item.kind + item.start">
                    <li>
                        <button type="button" @click="select(item.start)" class="w-full py-3 text-left hover:bg-gray-50" :class="item.past && 'opacity-50'">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold" x-text="item.name"></span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="item.official ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" x-text="item.official ? 'Chính thức' : 'Dự kiến'"></span>
                                <span x-show="item.badge" class="ml-auto rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800" x-text="item.badge"></span>
                            </span>
                            <span class="mt-0.5 block text-sm text-gray-700"><span x-text="item.range"></span> · <b x-text="`${item.length} ngày`"></b></span>
                            <span x-show="item.extra" class="block text-xs text-gray-500" x-text="item.extra"></span>
                            <span x-show="item.makeUp" class="block text-xs font-semibold text-gray-700" x-text="item.makeUp"></span>
                            <span x-show="item.source" class="block text-xs text-gray-400" x-text="item.source"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <p class="mt-2 text-xs text-gray-500">Theo lịch cán bộ, công chức (nghỉ thứ Bảy, Chủ nhật). <i>Dự kiến</i> là app tự tính theo Bộ luật Lao động, sẽ đổi khi có thông báo chính thức. Công ty có thể có lịch riêng.</p>
        </div>

        <ul x-show="listMode !== 'breaks'" class="mt-3 max-h-[28rem] divide-y divide-gray-100 overflow-y-auto">
            <template x-for="holiday in holidayList" :key="holiday.date + holiday.name">
                <li>
                    <button type="button" @click="select(holiday.date)" class="flex w-full items-center gap-3 py-2.5 text-left hover:bg-gray-50" :class="holiday.past && 'opacity-50'">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-lg" :class="holiday.off ? 'bg-rose-100' : 'bg-amber-50'" aria-hidden="true" x-text="holiday.icon"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-semibold" x-text="holiday.name"></span>
                                <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :class="holiday.off ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-500'" x-text="holiday.off ? 'Nghỉ làm' : 'Không nghỉ'"></span>
                            </span>
                            <span class="block text-xs text-gray-500" x-text="holiday.when"></span>
                        </span>
                        <span x-show="holiday.badge" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold" :class="holiday.daysLeft === 0 ? 'bg-amber-400 text-amber-950' : 'bg-emerald-100 text-emerald-800'" x-text="holiday.badge"></span>
                    </button>
                </li>
            </template>
            <li x-show="holidayList.length === 0" class="py-6 text-center text-sm text-gray-500">Không có ngày lễ nào.</li>
        </ul>
        <a x-show="listMode !== 'breaks'" :href="`${dataUrl}/${year}.ics`" class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:underline">📅 Lưu tất cả ngày lễ <span x-text="year"></span> vào lịch điện thoại</a>
    </div>

    {{-- Đổi ngày âm dương --}}
    <div class="h-fit rounded-3xl bg-white p-4 shadow-sm sm:p-6" x-effect="loadConverterYears()">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-bold">Đổi ngày</h3>
            <div class="flex gap-1 rounded-full bg-gray-100 p-1 text-xs font-semibold">
                <button type="button" @click="convMode = 'solar'" :class="convMode === 'solar' ? 'bg-white shadow' : 'text-gray-500'" class="rounded-full px-2.5 py-1">Dương → Âm</button>
                <button type="button" @click="convMode = 'lunar'" :class="convMode === 'lunar' ? 'bg-white shadow' : 'text-gray-500'" class="rounded-full px-2.5 py-1">Âm → Dương</button>
            </div>
        </div>

        <div x-show="convMode === 'solar'" class="mt-4">
            <label class="block text-sm text-gray-600">Ngày dương lịch
                <input type="date" x-model="convSolar" :min="`${minYear}-01-01`" :max="`${maxYear}-12-31`" class="mt-1 block w-full rounded-xl border-gray-200 text-lg font-semibold">
            </label>
        </div>

        <div x-show="convMode === 'lunar'" class="mt-4 space-y-3" style="display: none">
            <div class="grid grid-cols-3 gap-2">
                <label class="block text-sm text-gray-600">Ngày
                    <select x-model.number="convLunar.day" class="mt-1 block w-full rounded-xl border-gray-200 font-semibold">
                        <template x-for="d in 30" :key="d"><option :value="d" x-text="d" :selected="d === convLunar.day"></option></template>
                    </select>
                </label>
                <label class="block text-sm text-gray-600">Tháng
                    <select x-model.number="convLunar.month" class="mt-1 block w-full rounded-xl border-gray-200 font-semibold">
                        <template x-for="m in 12" :key="m"><option :value="m" x-text="m" :selected="m === convLunar.month"></option></template>
                    </select>
                </label>
                <label class="block text-sm text-gray-600">Năm
                    <input type="number" x-model.number="convLunar.year" :min="minYear" :max="maxYear - 1" class="mt-1 block w-full rounded-xl border-gray-200 font-semibold">
                </label>
            </div>
            <label x-show="convLeapMonth === convLunar.month" class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" x-model="convLunar.leap" class="rounded border-gray-300 text-emerald-600"> Tháng <span x-text="convLunar.month"></span> nhuận
            </label>
        </div>

        <div class="mt-4 rounded-2xl bg-emerald-50 p-4" aria-live="polite">
            <div class="text-sm text-gray-600" x-text="conversion.title"></div>
            <div class="text-xl font-bold text-emerald-800" x-text="conversion.main"></div>
            <div class="text-sm text-gray-600" x-text="conversion.sub"></div>
            <button type="button" x-show="conversion.iso" @click="select(conversion.iso)" class="mt-2 text-sm font-semibold text-emerald-700 hover:underline">Xem trên lịch →</button>
        </div>
    </div>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            // Mỗi ngày: [ngày âm, tháng âm, nhuận, năm âm, can ngày, chi ngày, can tháng, chi tháng, tiết khí, bắt đầu tiết, sao, pha trăng]
            // (App\Domain\LunarDayInfo::toCompact). Năm khác tải từ /lich/{năm}.json.
            window.Alpine.data('lunarCalendar', (options) => ({
                years: { [options.initial.year]: options.initial },
                dict: options.dictionary,
                today: options.today,
                selected: options.selected,
                year: options.year,
                month: options.month,
                dataUrl: options.dataUrl,
                pageUrl: options.pageUrl,
                minYear: {{ \App\Queries\YearCalendar::MIN_YEAR }},
                maxYear: {{ \App\Queries\YearCalendar::MAX_YEAR }},
                loading: false,
                picker: false,
                pickerYear: options.year,
                listMode: 'next',
                showGoodDays: false,
                copied: false,
                touchX: null,
                convMode: 'solar',
                convSolar: options.selected,
                convLunar: { day: 15, month: 8, year: Number(options.today.slice(0, 4)), leap: false },
                weekdays: ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'],
                init() {
                    const todayYear = Number(this.today.slice(0, 4));
                    [todayYear, todayYear + 1].forEach((year) => this.fetchYear(year));
                },
                dayData(iso) {
                    const [year, month, day] = iso.split('-').map(Number);

                    return this.years[year]?.months[month - 1]?.[day - 1] ?? null;
                },
                get cells() {
                    const data = this.years[this.year];
                    const blanks = (new Date(Date.UTC(this.year, this.month, 1)).getUTCDay() + 6) % 7;
                    const cells = Array.from({ length: blanks }, (_, i) => ({ key: `b${i}` }));

                    if (! data) {
                        return cells;
                    }

                    data.months[this.month].forEach(([lunarDay, lunarMonth, leap, , , , , , term, termStart, star], i) => {
                        const iso = this.iso(this.year, this.month, i + 1);
                        const holidays = this.holidaysOn(iso);
                        const work = this.workStatus(iso);
                        cells.push({
                            key: iso, iso, day: i + 1, lunarDay,
                            lunarText: lunarDay === 1 ? `${lunarDay}/${lunarMonth}${leap ? 'N' : ''}` : String(lunarDay),
                            term: termStart ? this.dict.terms[term] : '',
                            good: this.dict.stars[star][1],
                            sunday: (blanks + i) % 7 === 6,
                            holiday: holidays.length > 0,
                            dayOff: work.type === 'off',
                            holidayOff: holidays.length > 0 && work.type === 'off',
                            makeUp: work.type === 'makeup',
                            label: `Ngày ${i + 1}, âm lịch ${lunarDay}/${lunarMonth}${holidays.map((holiday) => ', ' + holiday.name).join('')}, ${work.text}`,
                        });
                    });

                    return cells;
                },
                get lunarRange() {
                    const days = this.years[this.year]?.months[this.month];

                    if (! days) {
                        return 'Đang tải…';
                    }

                    const first = days[0];
                    const last = days[days.length - 1];
                    const label = (day) => `${day[1]}${day[2] ? ' nhuận' : ''}`;
                    const months = first[1] === last[1] && first[2] === last[2] ? `Tháng ${label(first)}` : `Tháng ${label(first)} – ${label(last)}`;
                    const names = [...new Set([first[3], last[3]])].map((lunarYear) => this.yearName(lunarYear)).join(' – ');

                    return `${months} âm lịch · năm ${names}`;
                },
                get sel() {
                    const [year, month, day] = this.selected.split('-').map(Number);
                    const weekday = this.weekdays[this.parse(this.selected).getUTCDay()];
                    const base = { iso: this.selected, year, month, day, weekday, isToday: this.selected === this.today, url: `${this.pageUrl}/${this.selected}`, work: this.workStatus(this.selected) };
                    const data = this.dayData(this.selected);

                    if (! data) {
                        return { ...base, lunarDay: '…', lunarMonthLabel: '', dayName: '…', monthName: '…', yearName: '…', term: '…', moonIcon: '', moonName: '', good: false, starName: '…', special: '', hours: [], holidays: [] };
                    }

                    const [lunarDay, lunarMonth, leap, lunarYear, dayCan, dayChi, monthCan, monthChi, term, termStart, star, moon] = data;
                    const { can, chi, terms, stars, moons, hours, luckyHours } = this.dict;

                    return {
                        ...base,
                        lunarDay,
                        lunarMonthLabel: `Tháng ${lunarMonth}${leap ? ' nhuận' : ''}`,
                        dayName: `${can[dayCan]} ${chi[dayChi]}`,
                        monthName: `${can[monthCan]} ${chi[monthChi]}${leap ? ' (nhuận)' : ''}`,
                        yearName: this.yearName(lunarYear),
                        term: terms[term] + (termStart ? ' (bắt đầu hôm nay)' : ''),
                        moonIcon: moons[moon][0],
                        moonName: moons[moon][1],
                        good: stars[star][1],
                        starName: stars[star][0],
                        special: lunarDay === 1 ? 'Mùng 1' : (lunarDay === 15 ? 'Ngày rằm' : ''),
                        hours: luckyHours[dayChi].map((hour) => ({ name: chi[hour], range: hours[hour] })),
                        holidays: this.holidaysOn(this.selected),
                    };
                },
                get holidayList() {
                    const prefix = `${this.year}-${this.pad(this.month + 1)}`;
                    let nextFound = false;
                    let holidays;

                    if (this.listMode === 'next') {
                        holidays = Object.keys(this.years).sort().flatMap((year) => this.years[year].holidays)
                            .filter((holiday) => holiday.date >= this.today).slice(0, 8);
                    } else {
                        holidays = (this.years[this.year]?.holidays ?? []).filter((holiday) => this.listMode === 'year' || holiday.date.startsWith(prefix));
                    }

                    return holidays.map((holiday) => {
                        const [year, month, day] = holiday.date.split('-');
                        const daysLeft = Math.round((this.parse(holiday.date) - this.parse(this.today)) / 86400000);
                        let badge = '';

                        if (daysLeft >= 0 && ! nextFound) {
                            nextFound = true;
                            badge = daysLeft === 0 ? 'Hôm nay' : `Còn ${daysLeft} ngày`;
                        }

                        const weekday = this.weekdays[this.parse(holiday.date).getUTCDay()];

                        return { ...holiday, daysLeft, badge, off: Boolean(this.breakOn(holiday.date)), past: daysLeft < 0, when: `${weekday}, ${day}/${month}/${year}${holiday.lunar ? ' · ' + holiday.lunar : ''}` };
                    });
                },
                get convLeapMonth() {
                    for (const year of [this.convLunar.year, this.convLunar.year + 1]) {
                        for (const days of this.years[year]?.months ?? []) {
                            const leapDay = days.find((day) => day[2] === 1 && day[3] === this.convLunar.year);
                            if (leapDay) {
                                return leapDay[1];
                            }
                        }
                    }

                    return null;
                },
                get conversion() {
                    if (this.convMode === 'solar') {
                        const data = this.convSolar ? this.dayData(this.convSolar) : null;

                        if (! data) {
                            return { title: 'Âm lịch', main: this.convSolar ? 'Đang tải…' : 'Chọn một ngày', sub: '', iso: null };
                        }

                        const [lunarDay, lunarMonth, leap, lunarYear] = data;

                        return { title: 'Âm lịch', main: `${lunarDay}/${lunarMonth}${leap ? ' nhuận' : ''}/${lunarYear}`, sub: `Năm ${this.yearName(lunarYear)}`, iso: this.convSolar };
                    }

                    const { day, month, year } = this.convLunar;
                    const leap = this.convLunar.leap && this.convLeapMonth === month ? 1 : 0;

                    if (! this.years[year] || ! this.years[year + 1]) {
                        return { title: 'Dương lịch', main: year >= this.minYear && year < this.maxYear ? 'Đang tải…' : `Chọn năm ${this.minYear}–${this.maxYear - 1}`, sub: '', iso: null };
                    }

                    for (const solarYear of [year, year + 1]) {
                        for (const [monthIndex, days] of this.years[solarYear].months.entries()) {
                            const dayIndex = days.findIndex((d) => d[0] === day && d[1] === month && d[2] === leap && d[3] === year);
                            if (dayIndex >= 0) {
                                const iso = this.iso(solarYear, monthIndex, dayIndex + 1);

                                return { title: 'Dương lịch', main: `${this.weekdays[this.parse(iso).getUTCDay()]}, ${this.pad(dayIndex + 1)}/${this.pad(monthIndex + 1)}/${solarYear}`, sub: `Năm ${this.yearName(year)}`, iso };
                            }
                        }
                    }

                    return { title: 'Dương lịch', main: 'Không có ngày này', sub: `Tháng ${month}${leap ? ' nhuận' : ''} năm ${this.yearName(year)} chỉ có 29 ngày.`, iso: null };
                },
                loadConverterYears() {
                    if (this.convMode === 'solar' && this.convSolar) {
                        this.fetchYear(Number(this.convSolar.slice(0, 4)));
                    }
                    if (this.convMode === 'lunar' && this.convLunar.year >= this.minYear && this.convLunar.year < this.maxYear) {
                        this.fetchYear(this.convLunar.year);
                        this.fetchYear(this.convLunar.year + 1);
                    }
                },
                get allBreaks() {
                    const seen = new Set();

                    return Object.values(this.years).flatMap((data) => data.breaks ?? []).filter((item) => {
                        const key = `${item.kind}-${item.year}`;
                        if (seen.has(key)) {
                            return false;
                        }
                        seen.add(key);

                        return true;
                    });
                },
                breakOn(iso) {
                    return this.allBreaks.find((item) => item.days[iso]) ?? null;
                },
                workStatus(iso) {
                    const item = this.breakOn(iso);
                    const labels = { le: 'Được nghỉ lễ', bu: 'Được nghỉ bù', tuan: 'Nghỉ cuối tuần', them: 'Được nghỉ (hoán đổi)' };

                    if (item) {
                        return { type: 'off', text: `${labels[item.days[iso]]} · ${item.name}`, break: item };
                    }

                    const makeUp = this.allBreaks.find((entry) => entry.makeUpOn === iso);
                    if (makeUp) {
                        return { type: 'makeup', text: `Đi làm bù cho ${makeUp.name}`, break: makeUp };
                    }

                    const weekday = this.parse(iso).getUTCDay();

                    return weekday === 0 || weekday === 6 ? { type: 'weekend', text: 'Cuối tuần', break: null } : { type: 'work', text: 'Ngày đi làm bình thường', break: null };
                },
                get breakList() {
                    let nextFound = false;
                    const format = (iso) => `${['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][this.parse(iso).getUTCDay()]} ${iso.slice(8, 10)}/${iso.slice(5, 7)}`;

                    return this.allBreaks.filter((item) => item.year === this.year).sort((a, b) => a.start.localeCompare(b.start)).map((item) => {
                        const daysLeft = Math.round((this.parse(item.start) - this.parse(this.today)) / 86400000);
                        const ongoing = item.start <= this.today && item.end >= this.today;
                        let badge = '';

                        if (ongoing) {
                            badge = 'Đang nghỉ';
                        } else if (daysLeft > 0 && ! nextFound) {
                            nextFound = true;
                            badge = `Còn ${daysLeft} ngày`;
                        }

                        const extra = Object.entries(item.days).filter(([, reason]) => reason === 'bu' || reason === 'them')
                            .map(([iso, reason]) => `${reason === 'bu' ? 'nghỉ bù' : 'nghỉ thêm'} ${iso.slice(8, 10)}/${iso.slice(5, 7)}`).join(', ');

                        return {
                            ...item,
                            badge,
                            past: item.end < this.today,
                            range: `${format(item.start)} – ${format(item.end)}/${item.end.slice(0, 4)}`,
                            extra: extra ? `Gồm ${extra}` : '',
                            makeUp: item.makeUpOn ? `Đi làm bù ${format(item.makeUpOn)}` : '',
                            source: item.source ? `Nguồn: ${item.source}` : '',
                        };
                    });
                },
                yearName(lunarYear) {
                    return `${this.dict.can[(lunarYear + 6) % 10]} ${this.dict.chi[(lunarYear + 8) % 12]}`;
                },
                holidaysOn(iso) {
                    return (this.years[Number(iso.slice(0, 4))]?.holidays ?? []).filter((holiday) => holiday.date === iso);
                },
                async fetchYear(year) {
                    if (this.years[year] || year < this.minYear || year > this.maxYear) {
                        return;
                    }
                    try {
                        const response = await fetch(`${this.dataUrl}/${year}.json`);
                        if (response.ok) {
                            this.years[year] = await response.json();
                        }
                    } catch {
                        // Mất mạng: giữ nguyên những năm đã có.
                    }
                },
                async show(year, month) {
                    if (! this.years[year]) {
                        this.loading = true;
                        await this.fetchYear(year);
                        this.loading = false;
                    }
                    if (this.years[year]) {
                        this.year = year;
                        this.month = month;
                    }
                },
                async shift(step) {
                    const next = new Date(Date.UTC(this.year, this.month + step, 1));
                    await this.show(next.getUTCFullYear(), next.getUTCMonth());
                },
                async select(iso) {
                    await this.show(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1);
                    if (this.dayData(iso)) {
                        this.selected = iso;
                    }
                },
                goToday() {
                    this.select(this.today);
                },
                touchStart(event) {
                    this.touchX = event.changedTouches[0].clientX;
                },
                touchEnd(event) {
                    if (this.touchX === null) {
                        return;
                    }
                    const dx = event.changedTouches[0].clientX - this.touchX;
                    this.touchX = null;
                    if (Math.abs(dx) > 50) {
                        this.shift(dx < 0 ? 1 : -1);
                    }
                },
                async share() {
                    const sel = this.sel;
                    const text = `${sel.weekday}, ${this.pad(sel.day)}/${this.pad(sel.month)}/${sel.year} là ngày ${sel.lunarDay} ${sel.lunarMonthLabel.toLowerCase()} âm lịch, năm ${sel.yearName}.`;
                    try {
                        if (navigator.share) {
                            await navigator.share({ title: 'Lịch âm', text, url: sel.url });

                            return;
                        }
                        await navigator.clipboard.writeText(sel.url);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    } catch {
                        // Người dùng bấm hủy chia sẻ.
                    }
                },
                iso(year, month, day) {
                    return `${year}-${this.pad(month + 1)}-${this.pad(day)}`;
                },
                pad(value) {
                    return String(value).padStart(2, '0');
                },
                parse(isoDate) {
                    const [year, month, day] = isoDate.split('-').map(Number);

                    return new Date(Date.UTC(year, month - 1, day));
                },
            }));
        });
    </script>
@endonce
