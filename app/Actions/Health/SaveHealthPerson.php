<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthPerson;

/**
 * Thêm hoặc sửa một người trong sổ sức khỏe (document/09-gia-dinh.md GD-04).
 */
final class SaveHealthPerson
{
    /**
     * @param  array{name: string, birth_year?: int|null, note?: string|null}  $attributes
     */
    public function handle(?HealthPerson $person, array $attributes): HealthPerson
    {
        if ($person === null) {
            return HealthPerson::query()->create($attributes);
        }

        $person->fill($attributes)->save();

        return $person;
    }
}
