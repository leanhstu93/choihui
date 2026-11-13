<?php

declare(strict_types=1);

use App\Domain\TetCountdown;

/*
 * Đếm ngược tới Tết trên trang chủ.
 */

test('counts down to the next lunar new year', function (string $today, string $tet, int $daysLeft, string $name) {
    $countdown = TetCountdown::from(new DateTimeImmutable($today));

    expect($countdown->tet->format('Y-m-d'))->toBe($tet)
        ->and($countdown->daysLeft)->toBe($daysLeft)
        ->and($countdown->name())->toBe($name)
        ->and($countdown->isTet())->toBeFalse();
})->with([
    'autumn' => ['2026-11-13', '2027-02-06', 85, 'Đinh Mùi'],
    'eve' => ['2027-02-05', '2027-02-06', 1, 'Đinh Mùi'],
    'after the third day' => ['2027-02-09', '2028-01-26', 351, 'Mậu Thân'],
]);

test('the first three days of the year are Tết itself', function (string $today, int $tetDay) {
    $countdown = TetCountdown::from(new DateTimeImmutable($today));

    expect($countdown->isTet())->toBeTrue()
        ->and($countdown->tetDay)->toBe($tetDay)
        ->and($countdown->daysLeft)->toBe(0)
        ->and($countdown->name())->toBe('Đinh Mùi');
})->with([
    'mùng 1' => ['2027-02-06', 1],
    'mùng 3' => ['2027-02-08', 3],
]);

test('year names follow the sexagenary cycle', function (int $year, string $name) {
    expect(TetCountdown::yearName($year))->toBe($name);
})->with([
    [2024, 'Giáp Thìn'],
    [2025, 'Ất Tỵ'],
    [2026, 'Bính Ngọ'],
    [1984, 'Giáp Tý'],
]);
