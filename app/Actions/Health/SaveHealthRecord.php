<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthPerson;
use App\Models\HealthRecord;

/**
 * Thêm hoặc sửa một lần khám (document/09-gia-dinh.md GD-06).
 */
final class SaveHealthRecord
{
    /**
     * @param  array{visited_on: string, kind: string, title: string, place?: string|null, amount?: int|null, follow_up_on?: string|null, note?: string|null}  $attributes
     */
    public function handle(HealthPerson $person, ?HealthRecord $record, array $attributes): HealthRecord
    {
        if ($record === null) {
            return $person->records()->create($attributes);
        }

        $record->fill($attributes)->save();

        return $record;
    }
}
