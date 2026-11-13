<?php

declare(strict_types=1);

use App\Domain\CanChi;
use App\Domain\LunarDayInfo;

/*
 * Thông tin ngày kiểu lịch vạn niên: can chi, tiết khí, sao trực ngày, giờ hoàng đạo, pha trăng.
 */

function dayInfo(string $date): LunarDayInfo
{
    return LunarDayInfo::of(new DateTimeImmutable($date));
}

test('day, month and year names follow the sexagenary cycle', function () {
    expect(dayInfo('2000-01-01')->dayName())->toBe('Mậu Ngọ')
        ->and(dayInfo('2026-02-17')->monthName())->toBe('Canh Dần')
        ->and(dayInfo('2026-02-17')->yearName())->toBe('Bính Ngọ')
        ->and(dayInfo('2026-11-13')->dayName())->toBe('Tân Mão')
        ->and(CanChi::yearName(1984))->toBe('Giáp Tý');
});

test('solar terms start on their usual days', function (string $date, string $term) {
    $info = dayInfo($date);

    expect($info->solarTermName())->toBe($term)
        ->and($info->solarTermStarts)->toBeTrue()
        ->and(dayInfo((new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d'))->solarTermStarts)->toBeFalse();
})->with([
    ['2026-03-20', 'Xuân phân'],
    ['2026-11-07', 'Lập đông'],
    ['2026-12-22', 'Đông chí'],
    ['2027-02-04', 'Lập xuân'],
]);

test('lucky hours depend on the earthly branch of the day', function () {
    // Ngày Tý, Ngọ: Tý, Sửu, Mão, Ngọ, Thân, Dậu.
    expect(CanChi::luckyHours(0))->toBe([0, 1, 3, 6, 8, 9])
        ->and(CanChi::luckyHours(6))->toBe([0, 1, 3, 6, 8, 9])
        ->and(dayInfo('2000-01-01')->luckyHourLabels())->toBe(['Tý (23–1h)', 'Sửu (1–3h)', 'Mão (5–7h)', 'Ngọ (11–13h)', 'Thân (15–17h)', 'Dậu (17–19h)']);
});

test('the twelve day stars start with Thanh Long on the branch set by the lunar month', function () {
    // Tháng Giêng, tháng Bảy: Thanh Long ngày Tý; hoàng đạo là Tý, Sửu, Thìn, Tỵ, Mùi, Tuất.
    $goodBranches = array_values(array_filter(range(0, 11), fn (int $chi): bool => LunarDayInfo::STARS[LunarDayInfo::starOf(1, $chi)][1]));

    expect(LunarDayInfo::starOf(1, 0))->toBe(0)
        ->and(LunarDayInfo::starOf(7, 0))->toBe(0)
        ->and(LunarDayInfo::starOf(2, 2))->toBe(0)
        ->and($goodBranches)->toBe([0, 1, 4, 5, 7, 10]);
});

test('the moon is new on the first and full around the fifteenth', function () {
    expect(LunarDayInfo::moonPhaseOf(1))->toBe(0)
        ->and(LunarDayInfo::moonPhaseOf(15))->toBe(4)
        ->and(LunarDayInfo::moonPhaseOf(30))->toBe(7)
        ->and(dayInfo('2026-02-17')->moon())->toBe(['🌑', 'Trăng non']);
});
