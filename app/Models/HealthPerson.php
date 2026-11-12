<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\HealthPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một người trong sổ sức khỏe, không cần có tài khoản (document/09-gia-dinh.md G8).
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property int|null $birth_year
 * @property string|null $note
 * @property-read Collection<int, HealthRecord> $records
 */
#[Fillable(['name', 'birth_year', 'note'])]
class HealthPerson extends Model
{
    /** @use HasFactory<HealthPersonFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    protected $table = 'health_people';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
        ];
    }

    /**
     * @return HasMany<HealthRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    /**
     * Tuổi = năm nay − năm sinh (document/09-gia-dinh.md mục 4).
     */
    public function age(int $currentYear): ?int
    {
        return $this->birth_year === null || $this->birth_year > $currentYear ? null : $currentYear - $this->birth_year;
    }
}
