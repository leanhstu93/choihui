<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AdminOnly;
use App\Models\AdminLog;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Nhật ký quản trị (document/05-quan-tri.md QT-09), mới nhất trước.
 */
#[Layout('layouts.app')]
#[Title('Nhật ký · Quản trị')]
class LogIndex extends Component
{
    use AdminOnly, WithPagination;

    public function render(): View
    {
        return view('livewire.admin.log-index', [
            'logs' => AdminLog::query()->with('user:id,name,email')->latest('id')->paginate(30),
        ]);
    }
}
