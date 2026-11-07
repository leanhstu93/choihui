<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\RepairRecord;

/**
 * Một lần sửa kèm độ bền của lần thay trước cùng hạng mục (document/08-sua-chua.md mục 4).
 * lastedFor và lastedKm là null khi đây là lần đầu hoặc không tính được.
 */
final readonly class RepairEntry
{
    public function __construct(
        public RepairRecord $record,
        public ?string $lastedFor,
        public ?int $lastedKm,
    ) {}
}
