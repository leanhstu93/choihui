@php
    /** @var list<\App\Domain\LunarDayInfo> $days */
    $monthNumber = (int) $first->format('n');
    $yearNumber = (int) $first->format('Y');
    $weekdays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
    $firstLunar = $days[0]->lunar;
    $lastLunar = $days[count($days) - 1]->lunar;
    $title = "Lịch âm tháng {$monthNumber}/{$yearNumber}";
    $description = "Lịch âm dương tháng {$monthNumber} năm {$yearNumber}: từ ngày {$firstLunar->day}/{$firstLunar->month} đến {$lastLunar->day}/{$lastLunar->month} âm lịch, năm ".\App\Domain\CanChi::yearName($lastLunar->year)
        .'. Mùng 1, ngày rằm, ngày lễ, tiết khí, ngày hoàng đạo trong tháng.';
    $previousMonth = $first->modify('-1 month');
    $nextMonth = $first->modify('+1 month');
    $selected = $today->format('Y-m') === $first->format('Y-m') ? $today->format('Y-m-d') : $first->format('Y-m-d');
@endphp

<x-public-layout :title="$title" :description="$description" :canonical="route('lunar.month', [$yearNumber, $monthNumber])">
    <nav class="text-sm text-gray-500" aria-label="Đường dẫn">
        <a href="{{ route('lunar.today') }}" class="hover:text-emerald-700">Lịch âm</a> ›
        <a href="{{ route('lunar.year', $yearNumber) }}" class="hover:text-emerald-700">{{ $yearNumber }}</a>
    </nav>
    <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Lịch âm tháng {{ $monthNumber }} năm {{ $yearNumber }}</h1>
    <p class="mt-2 max-w-3xl text-gray-700">{{ $description }}</p>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        @if (\App\Queries\YearCalendar::supports((int) $previousMonth->format('Y')))
            <a href="{{ route('lunar.month', [(int) $previousMonth->format('Y'), (int) $previousMonth->format('n')]) }}" class="rounded-full bg-white px-4 py-2 font-semibold text-gray-700 shadow-sm hover:bg-gray-50">‹ Tháng {{ $previousMonth->format('n/Y') }}</a>
        @endif
        @if (\App\Queries\YearCalendar::supports((int) $nextMonth->format('Y')))
            <a href="{{ route('lunar.month', [(int) $nextMonth->format('Y'), (int) $nextMonth->format('n')]) }}" class="rounded-full bg-white px-4 py-2 font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Tháng {{ $nextMonth->format('n/Y') }} ›</a>
        @endif
    </div>

    <div class="mt-6">
        <x-lunar-calendar :calendar="$calendar" :today="$today" :selected="$selected" :month="$monthNumber" />
    </div>

    {{-- Bảng từng ngày (đọc được cả khi chưa chạy JavaScript) --}}
    <section class="mt-8 rounded-3xl bg-white p-4 shadow-sm sm:p-6">
        <h2 class="text-lg font-bold">Bảng âm lịch tháng {{ $monthNumber }}/{{ $yearNumber }}</h2>
        @if ($holidays !== [])
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($holidays as $holiday)
                    <li><span aria-hidden="true">{{ $holiday['icon'] }}</span> <b>{{ $holiday['date']->format('d/m') }}</b>: {{ $holiday['name'] }}@if ($holiday['lunar']) ({{ $holiday['lunar']->label() }})@endif{{ $holiday['dayOff'] ? ', nghỉ lễ' : '' }}</li>
                @endforeach
            </ul>
        @endif
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr><th class="py-2 pr-3">Dương lịch</th><th class="py-2 pr-3">Âm lịch</th><th class="py-2 pr-3">Can chi ngày</th><th class="py-2">Ghi chú</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($days as $day)
                        @php
                            $notes = array_filter([
                                $day->lunar->day === 1 ? 'Mùng 1' : null,
                                $day->lunar->day === 15 ? 'Rằm' : null,
                                $day->solarTermStarts ? 'Tiết '.$day->solarTermName() : null,
                                ...array_column(\App\Domain\VietnamHolidays::on($day->date), 'name'),
                            ]);
                        @endphp
                        <tr class="{{ $day->date->format('Y-m-d') === $today->format('Y-m-d') ? 'bg-amber-50' : '' }}">
                            <td class="py-2 pr-3"><a href="{{ route('lunar.day', $day->date->format('Y-m-d')) }}" class="font-medium text-emerald-700 hover:underline">{{ $weekdays[(int) $day->date->format('w')] }}, {{ $day->date->format('d/m') }}</a></td>
                            <td class="py-2 pr-3">{{ $day->lunar->label() }}</td>
                            <td class="py-2 pr-3">{{ $day->dayName() }} <span class="text-xs {{ $day->isGoodDay() ? 'text-emerald-700' : 'text-gray-400' }}">{{ $day->isGoodDay() ? 'hoàng đạo' : 'hắc đạo' }}</span></td>
                            <td class="py-2 text-gray-600">{{ implode(' · ', $notes) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @include('lunar.partials.cta')
</x-public-layout>
