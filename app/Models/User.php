<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property UserRole $role
 * @property Carbon|null $locked_at
 * @property Carbon|null $last_seen_at
 * @property-read int|null $hui_groups_count
 */
#[Fillable(['name', 'email', 'password', 'role', 'reminder_days_before'])]
#[Hidden(['password', 'remember_token', 'pin_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const LOCKED_MESSAGE = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reminder_days_before' => 3,
        'role' => 'hui_vien',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reminder_days_before' => 'integer',
            'pin_hash' => 'hashed',
            'last_reminded_on' => 'date',
            'role' => UserRole::class,
            'locked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Dây hụi của tài khoản. HuiGroup có global scope owner theo người đang
     * đăng nhập, nên ai đọc dây của người khác (trang quản trị) phải tự bỏ scope.
     *
     * @return HasMany<HuiGroup, $this>
     */
    public function huiGroups(): HasMany
    {
        return $this->hasMany(HuiGroup::class);
    }

    /**
     * Tài khoản chủ hụi (document/04-chu-hui.md C9).
     */
    public function isHost(): bool
    {
        return $this->role === UserRole::ChuHui;
    }

    /**
     * Quản trị viên: email nằm trong ADMIN_EMAILS (document/05-quan-tri.md Q3).
     */
    public function isAdmin(): bool
    {
        return in_array(Str::lower($this->email), config('hui.admin_emails'), true);
    }

    /**
     * Tài khoản bị quản trị viên khóa (document/05-quan-tri.md Q6).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
