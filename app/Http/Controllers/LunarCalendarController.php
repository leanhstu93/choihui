<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\LunarDayInfo;
use App\Domain\VietnamHolidays;
use App\Queries\YearCalendar;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Lịch âm công khai: trang hôm nay, trang ngày, tháng, năm (cho Google), dữ liệu năm dạng JSON
 * cho khối lịch và file .ics ngày lễ để lưu vào lịch điện thoại.
 */
final class LunarCalendarController
{
    public function __construct(private YearCalendar $calendar) {}

    public function today(): View
    {
        return $this->dayView(CarbonImmutable::today()->toDateTimeImmutable(), isToday: true);
    }

    public function day(string $date): View
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        abort_unless($parsed !== false && $parsed->format('Y-m-d') === $date && YearCalendar::supports((int) $parsed->format('Y')), 404);

        return $this->dayView($parsed, isToday: $date === CarbonImmutable::today()->toDateString());
    }

    public function month(int $year, int $month): View
    {
        abort_unless(YearCalendar::supports($year) && $month >= 1 && $month <= 12, 404);

        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $days = array_map(
            fn (int $day): LunarDayInfo => LunarDayInfo::of($first->setDate($year, $month, $day)),
            range(1, (int) $first->format('t')),
        );
        $holidays = array_values(array_filter(VietnamHolidays::forYear($year), fn (array $holiday): bool => (int) $holiday['date']->format('n') === $month));

        return view('lunar.month', [
            'first' => $first,
            'days' => $days,
            'holidays' => $holidays,
            'calendar' => $this->calendar->forYear($year),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function year(int $year): View
    {
        abort_unless(YearCalendar::supports($year), 404);

        return view('lunar.year', [
            'year' => $year,
            'holidays' => VietnamHolidays::forYear($year),
            'calendar' => $this->calendar->forYear($year),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function data(int $year): JsonResponse
    {
        abort_unless(YearCalendar::supports($year), 404);

        return response()->json($this->calendar->forYear($year))->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Ngày lễ của năm dạng iCalendar; ?ngay=Y-m-d chỉ lấy ngày lễ của ngày đó.
     */
    public function ics(Request $request, int $year): Response
    {
        abort_unless(YearCalendar::supports($year), 404);

        $day = $request->string('ngay')->toString();
        $holidays = array_values(array_filter(VietnamHolidays::forYear($year), fn (array $holiday): bool => $day === '' || $holiday['date']->format('Y-m-d') === $day));
        abort_if($holidays === [], 404);

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.config('app.name').'//Lich am//VI', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:'.$this->icsText('Ngày lễ '.$year)];
        $stamp = gmdate('Ymd\THis\Z');

        foreach ($holidays as $holiday) {
            $date = $holiday['date'];
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$date->format('Ymd').'-'.substr(hash('sha256', $holiday['name']), 0, 16).'@'.parse_url((string) config('app.url'), PHP_URL_HOST);
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART;VALUE=DATE:'.$date->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$date->modify('+1 day')->format('Ymd');
            $lines[] = 'SUMMARY:'.$this->icsText($holiday['name'].($holiday['lunar'] ? ' ('.$holiday['lunar']->label().')' : ''));
            $lines[] = 'DESCRIPTION:'.$this->icsText($holiday['about']);
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        $filename = $day === '' ? "ngay-le-{$year}.ics" : "ngay-le-{$day}.ics";

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function dayView(DateTimeImmutable $date, bool $isToday): View
    {
        $year = (int) $date->format('Y');

        return view('lunar.day', [
            'info' => LunarDayInfo::of($date),
            'holidays' => VietnamHolidays::on($date),
            'isToday' => $isToday,
            'calendar' => $this->calendar->forYear($year),
            'today' => CarbonImmutable::today(),
            'previous' => YearCalendar::supports((int) $date->modify('-1 day')->format('Y')) ? $date->modify('-1 day') : null,
            'next' => YearCalendar::supports((int) $date->modify('+1 day')->format('Y')) ? $date->modify('+1 day') : null,
        ]);
    }

    private function icsText(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }
}
