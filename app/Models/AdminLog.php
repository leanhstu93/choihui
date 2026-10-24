<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Một dòng nhật ký quản trị (document/05-quan-tri.md QT-09). Không có global
 * scope owner: chỉ màn quản trị đọc bảng này.
 *
 * @property int $id
 * @property int|null $admin_id
 * @property string $admin_email
 * @property int|null $user_id
 * @property AdminAction $action
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['admin_id', 'admin_email', 'user_id', 'action', 'meta'])]
class AdminLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AdminAction::class,
            'meta' => 'array',
        ];
    }

    /**
     * Tài khoản bị xem / bị thao tác.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
