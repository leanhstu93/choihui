<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Lớp kiểm tra thứ hai cho component quản trị (document/05-quan-tri.md mục 5),
 * chạy ở mọi request kể cả thao tác Livewire, bên cạnh middleware `admin`.
 */
trait AdminOnly
{
    public function bootAdminOnly(): void
    {
        abort_unless($this->admin()->isAdmin(), 404);
    }

    protected function admin(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
