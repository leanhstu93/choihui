<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Livewire\Concerns\AdminOnly;
use App\Models\HuiGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Danh sách tài khoản (document/05-quan-tri.md QT-03).
 */
#[Layout('layouts.app')]
#[Title('Tài khoản · Quản trị')]
class UserIndex extends Component
{
    use AdminOnly, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: false)]
    public bool $lockedOnly = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'lockedOnly'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $term = trim($this->search);
        $role = UserRole::tryFrom($this->role);

        $users = User::query()
            ->withCount(['huiGroups' => fn (Builder $query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE)])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role))
            ->when($this->lockedOnly, fn (Builder $query) => $query->whereNotNull('locked_at'))
            ->latest()
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.user-index', [
            'users' => $users,
            'roles' => UserRole::cases(),
        ]);
    }
}
