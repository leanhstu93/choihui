<?php

declare(strict_types=1);

use App\Domain\PaymentStatus;
use App\Enums\CashEntryStatus;

test('payment status reflects amount and due date', function (int $actual, ?string $paidDate, CashEntryStatus $expected) {
    $status = PaymentStatus::forPayment(
        expected: 900_000,
        actual: $actual,
        paidDate: $paidDate === null ? null : new DateTimeImmutable($paidDate),
        dueDate: new DateTimeImmutable('2026-03-05'),
    );

    expect($status)->toBe($expected);
})->with([
    'nothing paid' => [0, null, CashEntryStatus::Chua],
    'paid in full on the due date' => [900_000, '2026-03-05', CashEntryStatus::Du],
    'paid more than expected' => [1_000_000, '2026-03-01', CashEntryStatus::Du],
    'paid in full after the due date' => [900_000, '2026-03-06', CashEntryStatus::Muon],
    'paid short' => [500_000, '2026-03-01', CashEntryStatus::Thieu],
]);

test('receipt status reflects amount received', function (int $actual, bool $writtenOff, CashEntryStatus $expected) {
    expect(PaymentStatus::forReceipt(expected: 8_300_000, actual: $actual, writtenOff: $writtenOff))->toBe($expected);
})->with([
    'nothing received' => [0, false, CashEntryStatus::Chua],
    'received in full' => [8_300_000, false, CashEntryStatus::Du],
    'received short' => [6_000_000, false, CashEntryStatus::ConNo],
    'shortfall written off' => [6_000_000, true, CashEntryStatus::Bung],
    'nothing received and written off' => [0, true, CashEntryStatus::Bung],
]);
