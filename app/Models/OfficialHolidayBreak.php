<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HolidayBreakKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Lịch nghỉ lễ chính thức của một đợt trong năm, do quản trị nhập. Dùng chung cho mọi người,
 * không có chủ sở hữu.
 *
 * @property string $id
 * @property int $year
 * @property HolidayBreakKind $kind
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable|null $make_up_on
 * @property string|null $source
 */
#[Fillable(['year', 'kind', 'starts_on', 'ends_on', 'make_up_on', 'source'])]
class OfficialHolidayBreak extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'kind' => HolidayBreakKind::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'make_up_on' => 'immutable_date',
        ];
    }
}
