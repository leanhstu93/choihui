<?php

declare(strict_types=1);

use App\Domain\VietnamHolidays;

/*
 * Ngày lễ trong năm cho phần lịch trên trang giới thiệu.
 */

/**
 * @return array<string, string> tên → Y-m-d
 */
function holidaysIn(int $year): array
{
    $byName = [];
    foreach (VietnamHolidays::forYear($year) as $holiday) {
        $byName[$holiday['name']] = $holiday['date']->format('Y-m-d');
    }

    return $byName;
}

test('lunar holidays fall on their solar dates', function () {
    expect(holidaysIn(2026))
        ->toMatchArray([
            'Giao thừa' => '2026-02-16',
            'Tết Nguyên đán' => '2026-02-17',
            'Giỗ Tổ Hùng Vương' => '2026-04-26',
            'Tết Trung thu' => '2026-09-25',
            'Quốc khánh' => '2026-09-02',
        ]);
});

test('only days inside the solar year are listed, sorted by date', function () {
    $holidays = VietnamHolidays::forYear(2026);
    $dates = array_map(fn (array $holiday): string => $holiday['date']->format('Y-m-d'), $holidays);

    expect($dates)->each->toStartWith('2026-')
        ->and($dates)->toBe(collect($dates)->sort()->values()->all())
        // Ông Táo của năm âm Ất Tỵ rơi vào 2026, của năm Bính Ngọ sang 2027.
        ->and(holidaysIn(2026)['Ông Công Ông Táo'])->toBe('2026-02-10')
        ->and(collect($holidays)->where('name', 'Ông Công Ông Táo'))->toHaveCount(1);
});

test('statutory days off are marked', function () {
    $daysOff = collect(VietnamHolidays::forYear(2027))->where('dayOff', true)->pluck('name')->all();

    expect($daysOff)->toBe(['Tết Dương lịch', 'Giao thừa', 'Tết Nguyên đán', 'Mùng 2 Tết', 'Mùng 3 Tết', 'Giỗ Tổ Hùng Vương', 'Giải phóng miền Nam', 'Quốc tế Lao động', 'Quốc khánh', 'Ngày Văn hóa Việt Nam']);
});
