<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\FamilyEvent;
use Carbon\CarbonImmutable;

/**
 * Lần tới của một ngày nhớ (document/09-gia-dinh.md GD-02).
 */
final readonly class FamilyOccurrence
{
    public function __construct(
        public FamilyEvent $event,
        public CarbonImmutable $date,
        public int $daysLeft,
        public string $lunarLabel,
        public ?string $yearsLabel,
    ) {}

    /**
     * "Hôm nay", "Ngày mai", "Còn 5 ngày".
     */
    public function countdown(): string
    {
        return match ($this->daysLeft) {
            0 => 'Hôm nay',
            1 => 'Ngày mai',
            default => "Còn {$this->daysLeft} ngày",
        };
    }
}
