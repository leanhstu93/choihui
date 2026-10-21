<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Queries\ProfitReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Báo cáo lời/lỗ từng dây và tổng, thực tế và dự kiến (FR-10).
 */
#[Layout('layouts.app')]
#[Title('Báo cáo')]
class ProfitReportPage extends Component
{
    public function render(ProfitReport $profitReport): View
    {
        return view('livewire.reports.profit-report-page', $profitReport->forUser(Auth::user()));
    }
}
