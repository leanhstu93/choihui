<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserRole $role
 */
#[Fillable(['name', 'email', 'password', 'role', 'reminder_days_before'])]
#[Hidden(['password', 'remember_token', 'pin_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    /**
     * Tài khoản chủ hụi (document/04-chu-hui.md C9).
     */
    public function isHost(): bool
    {
        return $this->role === UserRole::ChuHui;
    }
}
