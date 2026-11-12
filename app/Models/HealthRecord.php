<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthRecordKind;
use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\HealthRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lần khám, tiêm hoặc xét nghiệm (document/09-gia-dinh.md G9).
 *
 * @property string $id
 * @property int $user_id
 * @property string $health_person_id
 * @property CarbonImmutable $visited_on
 * @property HealthRecordKind $kind
 * @property string $title
 * @property string|null $place
 * @property int|null $amount
 * @property CarbonImmutable|null $follow_up_on
 * @property string|null $note
 * @property-read HealthPerson $person
 * @property-read Collection<int, HealthRecordPhoto> $photos
 */
#[Fillable(['visited_on', 'kind', 'title', 'place', 'amount', 'follow_up_on', 'note'])]
class HealthRecord extends Model
{
    /** @use HasFactory<HealthRecordFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const MAX_PHOTOS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_on' => 'immutable_date',
            'kind' => HealthRecordKind::class,
            'amount' => 'integer',
            'follow_up_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<HealthPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(HealthPerson::class, 'health_person_id');
    }

    /**
     * @return HasMany<HealthRecordPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(HealthRecordPhoto::class);
    }
}
