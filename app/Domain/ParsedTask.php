<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\TaskRepeat;
use DateTimeImmutable;

/**
 * Kết quả đọc một dòng thêm nhanh (document/10-viec-can-lam.md V7).
 */
final readonly class ParsedTask
{
    /**
     * @param  list<array{kind: string, text: string}>  $matches  Phần đã hiểu, theo thứ tự trong câu, để hiện chip
     */
    public function __construct(
        public string $title,
        public ?DateTimeImmutable $dueOn = null,
        public ?string $dueTime = null,
        public bool $important = false,
        public TaskRepeat $repeat = TaskRepeat::None,
        public int $weekdays = 0,
        public ?int $repeatDay = null,
        public array $matches = [],
    ) {}
}
