<?php

declare(strict_types=1);

namespace App\Queries;

use DateTimeImmutable;

/**
 * Một khoản phải đóng sắp đến hạn của một tay.
 */
final readonly class UpcomingPayment
{
    public function __construct(
        public string $groupId,
        public string $groupName,
        public string $roundId,
        public int $roundNo,
        public string $slotLabel,
        public DateTimeImmutable $dueDate,
        public int $amount,
        public bool $isEstimate,
        public ?string $cashEntryId,
    ) {}

    public function isOverdue(DateTimeImmutable $today): bool
    {
        return $this->dueDate->format('Y-m-d') < $today->format('Y-m-d');
    }
}
