@php
    /** @var \App\Domain\LunarDayInfo $info */
    $date = $info->date;
    $weekday = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'][(int) $date->format('w')];
    $solar = $date->format('d/m/Y');
    $lunarText = $info->lunar->day.'/'.$info->lunar->month.($info->lunar->isLeap ? ' nhuận' : '').'/'.$info->lunar->year;
    $heading = $isToday ? "Lịch âm hôm nay, {$weekday} {$solar}" : "Âm lịch ngày {$solar}";
    $title = $isToday ? "Lịch âm hôm nay {$solar}: ngày {$lunarText} âm lịch" : "Âm lịch ngày {$solar} là ngày {$lunarText}";
    $holidayNames = implode(', ', array_column($holidays, 'name'));
    $description = "{$weekday}, {$solar} dương lịch là ngày {$lunarText} âm lịch, ngày {$info->dayName()}, tháng {$info->monthName()}, năm {$info->yearName()}."
        .($holidayNames !== '' ? " {$holidayNames}." : '').' Tiết '.$info->solarTermName().', '.($info->isGoodDay() ? 'ngày hoàng đạo' : 'ngày hắc đạo').', giờ hoàng đạo: '.implode(', ', $info->luckyHourLabels()).'.';
    $monthNumber = (int) $date->format('n');
    $yearNumber = (int) $date->format('Y');
@endphp

<x-public-layout :title="$title" :description="$description" :canonical="$isToday ? route('lunar.today') : route('lunar.day', $date->format('Y-m-d'))">
    <nav class="text-sm text-gray-500" aria-label="Đường dẫn">
        <a href="{{ route('lunar.today') }}" class="hover:text-emerald-700">Lịch âm</a> ›
        <a href="{{ route('lunar.year', $yearNumber) }}" class="hover:text-emerald-700">{{ $yearNumber }}</a> ›
        <a href="{{ route('lunar.month', [$yearNumber, $monthNumber]) }}" class="hover:text-emerald-700">Tháng {{ $monthNumber }}</a>
    </nav>

    <h1 class="mt-2 text-2xl font-bold sm:text-3xl">{{ $heading }}</h1>
    <p class="mt-2 max-w-3xl text-gray-700">{{ $description }}</p>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        @if ($previous)
            <a href="{{ route('lunar.day', $previous->format('Y-m-d')) }}" class="rounded-full bg-white px-4 py-2 font-semibold text-gray-700 shadow-sm hover:bg-gray-50">‹ {{ $previous->format('d/m') }}</a>
        @endif
        @unless ($isToday)
            <a href="{{ route('lunar.today') }}" class="rounded-full bg-white px-4 py-2 font-semibold text-emerald-700 shadow-sm hover:bg-gray-50">Hôm nay</a>
        @endunless
        @if ($next)
            <a href="{{ route('lunar.day', $next->format('Y-m-d')) }}" class="rounded-full bg-white px-4 py-2 font-semibold text-gray-700 shadow-sm hover:bg-gray-50">{{ $next->format('d/m') }} ›</a>
        @endif
    </div>

    <div class="mt-6">
        <x-lunar-calendar :calendar="$calendar" :today="$today" :selected="$date->format('Y-m-d')" />
    </div>

    @include('lunar.partials.cta')
</x-public-layout>
