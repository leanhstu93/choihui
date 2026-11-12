<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Models\FamilyEvent;

/**
 * Xóa một ngày nhớ (document/09-gia-dinh.md GD-01).
 */
final class DeleteFamilyEvent
{
    public function handle(FamilyEvent $event): void
    {
        $event->delete();
    }
}
