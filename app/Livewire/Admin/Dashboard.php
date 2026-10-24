<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AdminOnly;
use App\Queries\AdminStats;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tổng quan quản trị (document/05-quan-tri.md QT-02).
 */
#[Layout('layouts.app')]
#[Title('Quản trị')]
class Dashboard extends Component
{
    use AdminOnly;

    public function render(AdminStats $adminStats): View
    {
        return view('livewire.admin.dashboard', [
            'stats' => $adminStats->overview(now()->toDateTimeImmutable()),
        ]);
    }
}
