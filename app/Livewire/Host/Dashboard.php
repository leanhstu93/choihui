<?php

declare(strict_types=1);

namespace App\Livewire\Host;

use App\Actions\Host\MarkCollected;
use App\Actions\Host\MarkPaidOut;
use App\Enums\HostDirection;
use App\Exceptions\HuiActionException;
use App\Models\HostEntry;
use App\Queries\HostDashboard;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Trang chủ chủ hụi (CH-08): còn phải thu, chưa chi, tay chưa đóng theo từng kỳ,
 * kỳ cần nhập kết quả; thu / chi 1 chạm.
 */
#[Layout('layouts.app')]
#[Title('Trang chủ')]
class Dashboard extends Component
{
    public const DAYS_AHEAD = 30;

    public ?string $actionError = null;

    /**
     * Ghi đã thu / đã chi đủ, hôm nay (thao tác 1 chạm).
     */
    public function settleInFull(string $entryId, MarkCollected $markCollected, MarkPaidOut $markPaidOut): void
    {
        $entry = HostEntry::query()->findOrFail($entryId);
        Gate::authorize('update', $entry);
        $this->actionError = null;
        $today = new DateTimeImmutable('today');

        try {
            if ($entry->direction === HostDirection::Chi) {
                $markPaidOut->handle($entry, $entry->amount_expected, $today);
            } else {
                $markCollected->handle($entry, $entry->amount_expected, $today);
            }
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }

    public function render(HostDashboard $hostDashboard): View
    {
        $today = new DateTimeImmutable('today');

        return view('livewire.host.dashboard', [
            'today' => $today,
            ...$hostDashboard->forUser(Auth::user(), $today, $today->modify('+'.self::DAYS_AHEAD.' days')),
        ]);
    }
}
