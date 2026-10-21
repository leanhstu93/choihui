<?php

declare(strict_types=1);

namespace App\Livewire\Host;

use App\Queries\HostReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Báo cáo chủ hụi (CH-12): thảo đã nhận / dự kiến, mình bù, nợ, bùng, lời lỗ —
 * từng dây và tổng.
 */
#[Layout('layouts.app')]
#[Title('Báo cáo')]
class ReportPage extends Component
{
    public function render(HostReport $hostReport): View
    {
        return view('livewire.host.report-page', $hostReport->forUser(Auth::user()));
    }
}
