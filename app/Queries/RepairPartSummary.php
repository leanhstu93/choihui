<?php

declare(strict_types=1);

namespace App\Queries;

/**
 * Tổng hợp một hạng mục trên một món đồ (document/08-sua-chua.md SC-05). inUseFor là thời
 * gian từ lần gần nhất đến hôm nay, null khi lần đó là hôm nay.
 */
final readonly class RepairPartSummary
{
    public function __construct(
        public string $name,
        public int $count,
        public int $total,
        public ?string $inUseFor,
    ) {}
}
