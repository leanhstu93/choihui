<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;

/*
 * Trang lịch âm công khai: hôm nay, ngày, tháng, năm, dữ liệu năm và file .ics.
 */

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00')));

test('the today page shows today in lunar terms with a canonical link', function () {
    $this->get(route('lunar.today'))->assertOk()
        ->assertSee('Lịch âm hôm nay, Thứ Sáu 13/11/2026')
        ->assertSee('ngày 5/10/2026 âm lịch, ngày Tân Mão, tháng Kỷ Hợi, năm Bính Ngọ')
        ->assertSee('<link rel="canonical" href="'.route('lunar.today').'">', false);
});

test('a day page describes the day and its holidays', function () {
    $this->get(route('lunar.day', '2027-02-06'))->assertOk()
        ->assertSee('Âm lịch ngày 06/02/2027')
        ->assertSee('ngày 1/1/2027 âm lịch')
        ->assertSee('Tết Nguyên đán')
        ->assertSee(route('lunar.day', '2027-02-05'))
        ->assertSee(route('lunar.day', '2027-02-07'));
});

test('a month page lists every day with its lunar date', function () {
    $this->get(route('lunar.month', [2026, 9]))->assertOk()
        ->assertSee('Lịch âm tháng 9 năm 2026')
        ->assertSee('15/8 ÂL')
        ->assertSee('Tết Trung thu')
        ->assertSee(route('lunar.day', '2026-09-30'));
});

test('a year page lists the holidays with their meaning', function () {
    $this->get(route('lunar.year', 2027))->assertOk()
        ->assertSee('Lịch âm 2027, năm Đinh Mùi')
        ->assertSee('Tết Nguyên đán rơi vào 06/02/2027')
        ->assertSee('Tưởng nhớ các Vua Hùng')
        ->assertSee(route('lunar.month', [2027, 12]));
});

test('dates outside the supported range or that do not exist are not found', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/lich-am/2026-02-30',
    '/lich-am/1949-12-31',
    '/lich-am/2101',
    '/lich-am/2026/13',
    '/lich/1900.json',
    '/lich/2101.ics',
]);

test('other years load as json for the calendar', function () {
    $this->getJson(route('lunar.data', 2027))->assertOk()
        ->assertJsonPath('year', 2027)
        ->assertJsonPath('months.1.5.0', 1)
        ->assertJsonPath('months.1.5.1', 1)
        ->assertJsonFragment(['name' => 'Tết Nguyên đán', 'date' => '2027-02-06']);
});

test('holidays download as an iCalendar file, for the year or a single day', function () {
    $year = $this->get(route('lunar.ics', 2027))->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="ngay-le-2027.ics"')
        ->getContent();

    expect($year)->toContain("BEGIN:VCALENDAR\r\n")
        ->toContain("DTSTART;VALUE=DATE:20270206\r\nDTEND;VALUE=DATE:20270207\r\nSUMMARY:Tết Nguyên đán (1/1 ÂL)")
        ->and(substr_count($year, 'BEGIN:VEVENT'))->toBe(29);

    $day = $this->get(route('lunar.ics', ['year' => 2027, 'ngay' => '2027-09-02']))->assertOk()->getContent();
    expect(substr_count($day, 'BEGIN:VEVENT'))->toBe(1)->and($day)->toContain('SUMMARY:Quốc khánh');

    $this->get(route('lunar.ics', ['year' => 2027, 'ngay' => '2027-09-03']))->assertNotFound();
});

test('the sitemap lists the lunar pages and robots keep the data files out', function () {
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.route('lunar.today').'</loc>', false)
        ->assertSee('<loc>'.route('lunar.year', 2027).'</loc>', false)
        ->assertSee('<loc>'.route('lunar.month', [2025, 1]).'</loc>', false)
        ->assertSee('<loc>'.route('lunar.day', '2027-12-31').'</loc>', false)
        ->assertDontSee('<loc>'.route('lunar.day', '2028-01-01').'</loc>', false);

    $this->get('/robots.txt')->assertSee('Disallow: /lich/');
});
