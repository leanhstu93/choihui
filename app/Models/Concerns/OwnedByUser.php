<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Lớp bảo vệ thứ nhất (đặc tả mục 14): mọi truy vấn model chỉ thấy dòng của
 * người đang đăng nhập; khi không có ai đăng nhập thì không thấy dòng nào.
 *
 * Global scope không bảo vệ truy vấn thô (DB::table, DB::select) và
 * withoutGlobalScopes(); hai cách này phải tự thêm điều kiện user_id.
 */
trait OwnedByUser
{
    public const OWNER_SCOPE = 'owner';

    protected static function bootOwnedByUser(): void
    {
        static::addGlobalScope(self::OWNER_SCOPE, function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('user_id'), Auth::id());
        });

        static::creating(function (Model $model): void {
            if (Auth::check()) {
                $model->setAttribute('user_id', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kiểm tra dòng này có thuộc về người dùng đã cho hay không.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
