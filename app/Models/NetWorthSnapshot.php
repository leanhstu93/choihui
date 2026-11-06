<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\NetWorthSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Số tổng tài sản của một tháng (document/07-tong-tai-san.md T3, T5).
 *
 * @property string $id
 * @property int $user_id
 * @property string $month
 * @property int $assets
 * @property int $debts
 */
#[Fillable(['month', 'assets', 'debts'])]
class NetWorthSnapshot extends Model
{
    /** @use HasFactory<NetWorthSnapshotFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assets' => 'integer',
            'debts' => 'integer',
        ];
    }

    public function net(): int
    {
        return $this->assets - $this->debts;
    }
}
