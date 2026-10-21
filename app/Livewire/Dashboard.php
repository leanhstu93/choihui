<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Payments\MarkPaid;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Queries\UpcomingPayment;
use App\Queries\UpcomingPayments;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Trang chủ "Sắp đến hạn" (FR-08): tổng cần đóng 30 ngày tới, các khoản gom theo
 * hạn đóng, đánh dấu đã đóng 1 chạm.
 */
#[Layout('layouts.app')]
#[Title('Trang chủ')]
class Dashboard extends Component
{
    public const DAYS_AHEAD = 30;

    public ?string $actionError = null;

    /**
     * Sau đăng nhập mọi người về đây; chủ hụi có trang chủ riêng (CH-08).
     */
    public function mount(): void
    {
        if (Auth::user()?->isHost()) {
            $this->redirectRoute('host.dashboard', navigate: true);
        }
    }

    public function payInFull(string $entryId, MarkPaid $markPaid): void
    {
        $entry = CashEntry::query()->findOrFail($entryId);
        Gate::authorize('update', $entry);
        $this->actionError = null;

        try {
            $markPaid->handle($entry, $entry->amount_expected, new DateTimeImmutable('today'));
        } catch (HuiActionException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }

    public function render(UpcomingPayments $upcomingPayments): View
    {
        $today = new DateTimeImmutable('today');
        $payments = $upcomingPayments->forUser(Auth::user(), $today->modify('+'.self::DAYS_AHEAD.' days'));

        return view('livewire.dashboard', [
            'today' => $today,
            'paymentsByDate' => collect($payments)->groupBy(fn (UpcomingPayment $payment) => $payment->dueDate->format('Y-m-d')),
            'totalConfirmed' => array_sum(array_map(fn (UpcomingPayment $payment) => $payment->isEstimate ? 0 : $payment->amount, $payments)),
            'totalEstimated' => array_sum(array_map(fn (UpcomingPayment $payment) => $payment->isEstimate ? $payment->amount : 0, $payments)),
            'overdueCount' => count(array_filter($payments, fn (UpcomingPayment $payment) => $payment->isOverdue($today))),
        ]);
    }
}
