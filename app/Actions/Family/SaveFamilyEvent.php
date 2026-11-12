<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Models\FamilyEvent;

/**
 * Thêm hoặc sửa một ngày nhớ (document/09-gia-dinh.md GD-01).
 */
final class SaveFamilyEvent
{
    /**
     * @param  array{title: string, kind: string, calendar: string, day: int, month: int, origin_year?: int|null, remind_days_before: int, note?: string|null}  $attributes
     */
    public function handle(?FamilyEvent $event, array $attributes): FamilyEvent
    {
        if ($event === null) {
            return FamilyEvent::query()->create($attributes);
        }

        $event->fill($attributes)->save();

        return $event;
    }
}
