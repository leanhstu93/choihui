<?php

declare(strict_types=1);

namespace App\Livewire\NetWorth;

use App\Actions\NetWorth\RecordNetWorthSnapshot;
use App\Models\NetWorthSnapshot;
use App\Models\User;
use App\Queries\NetWorthReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Trang tổng tài sản (document/07-tong-tai-san.md): chỉ đọc, mỗi lần mở cập nhật số của tháng.
 */
#[Layout('layouts.app')]
#[Title('Tổng tài sản')]
class NetWorthPage extends Component
{
    public const CHART_MONTHS = 12;

    public function mount(NetWorthReport $netWorthReport, RecordNetWorthSnapshot $recordSnapshot): void
    {
        $report = $netWorthReport->forUser($this->user());
        $recordSnapshot->handle($report['assets'], $report['debts']);
    }

    public function render(NetWorthReport $netWorthReport): View
    {
        return view('livewire.net-worth.net-worth-page', $netWorthReport->forUser($this->user()) + [
            'isHost' => $this->user()->isHost(),
            'snapshots' => NetWorthSnapshot::query()->orderByDesc('month')->limit(self::CHART_MONTHS)->get()->reverse()->values(),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
