@php
    $yearName = \App\Domain\CanChi::yearName($year);
    $title = "Lịch âm {$year} và các ngày lễ năm {$yearName}";
    $tet = collect($holidays)->firstWhere('name', 'Tết Nguyên đán');
    $description = "Lịch âm dương năm {$year} (năm {$yearName}): ".($tet ? 'Tết Nguyên đán rơi vào '.$tet['date']->format('d/m/Y').'. ' : '').'Danh sách ngày lễ, ngày nghỉ lễ trong năm và lịch từng tháng.';
    $weekdays = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
@endphp

<x-public-layout :title="$title" :description="$description" :canonical="route('lunar.year', $year)">
    <nav class="text-sm text-gray-500" aria-label="Đường dẫn">
        <a href="{{ route('lunar.today') }}" class="hover:text-emerald-700">Lịch âm</a>
    </nav>
    <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Lịch âm {{ $year }}, năm {{ $yearName }}</h1>
    <p class="mt-2 max-w-3xl text-gray-700">{{ $description }}</p>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        @foreach ([$year - 1, $year + 1] as $otherYear)
            @if (\App\Queries\YearCalendar::supports($otherYear))
                <a href="{{ route('lunar.year', $otherYear) }}" class="rounded-full bg-white px-4 py-2 font-semibold text-gray-700 shadow-sm hover:bg-gray-50">{{ $otherYear < $year ? '‹ ' : '' }}Năm {{ $otherYear }}{{ $otherYear > $year ? ' ›' : '' }}</a>
            @endif
        @endforeach
    </div>

    {{-- 12 tháng --}}
    <section class="mt-6">
        <h2 class="text-lg font-bold">Lịch từng tháng</h2>
        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach (range(1, 12) as $monthNumber)
                <a href="{{ route('lunar.month', [$year, $monthNumber]) }}" class="rounded-2xl bg-white py-3 text-center font-semibold shadow-sm hover:bg-emerald-50 {{ $today->year === $year && $today->month === $monthNumber ? 'ring-2 ring-amber-400' : '' }}">Tháng {{ $monthNumber }}</a>
            @endforeach
        </div>
    </section>

    {{-- Ngày lễ trong năm --}}
    <section class="mt-8 rounded-3xl bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-bold">Ngày lễ năm {{ $year }}</h2>
            <a href="{{ route('lunar.ics', $year) }}" class="text-sm font-semibold text-emerald-700 hover:underline">📅 Lưu tất cả vào lịch điện thoại</a>
        </div>
        <ul class="mt-3 divide-y divide-gray-100">
            @foreach ($holidays as $holiday)
                <li class="flex gap-3 py-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xl {{ $holiday['dayOff'] ? 'bg-rose-100' : 'bg-amber-50' }}" aria-hidden="true">{{ $holiday['icon'] }}</span>
                    <div class="min-w-0">
                        <h3 class="font-semibold">
                            <a href="{{ route('lunar.day', $holiday['date']->format('Y-m-d')) }}" class="hover:text-emerald-700">{{ $holiday['name'] }}</a>
                            @if ($holiday['dayOff'])
                                <span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700">Nghỉ lễ</span>
                            @endif
                        </h3>
                        <div class="text-sm text-gray-500">{{ $weekdays[(int) $holiday['date']->format('w')] }}, {{ $holiday['date']->format('d/m/Y') }}@if ($holiday['lunar']) · {{ $holiday['lunar']->label() }}@endif</div>
                        <p class="mt-1 text-sm text-gray-700">{{ $holiday['about'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-gray-500">Lịch nghỉ Tết cụ thể do Chính phủ công bố hằng năm.</p>
    </section>

    <div class="mt-8">
        <x-lunar-calendar :calendar="$calendar" :today="$today" :selected="$today->year === $year ? $today->format('Y-m-d') : $year.'-01-01'" />
    </div>

    @include('lunar.partials.cta')
</x-public-layout>
