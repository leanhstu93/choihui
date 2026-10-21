<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Models\HuiGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Danh sách dây hụi, lọc theo trạng thái (document/01-dac-ta.md mục 7).
 */
#[Layout('layouts.app')]
#[Title('Dây hụi')]
class GroupIndex extends Component
{
    #[Url(as: 'trang-thai')]
    public string $status = 'dang_chay';

    public function mount(): void
    {
        Gate::authorize('viewAny', HuiGroup::class);
    }

    /**
     * @return Collection<int, HuiGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return HuiGroup::query()
            ->when(HuiGroupStatus::tryFrom($this->status), fn (Builder $query, HuiGroupStatus $status) => $query->where('status', $status))
            ->withCount([
                'slots',
                'rounds as recorded_rounds_count' => fn (Builder $query) => $query->whereNotNull('bid_amount'),
                'rounds as won_rounds_count' => fn (Builder $query) => $query->where('winner_type', WinnerType::Minh),
                'cashEntries as unpaid_entries_count' => fn (Builder $query) => $query
                    ->where('direction', CashDirection::Dong)
                    ->where('cash_entries.status', CashEntryStatus::Chua),
            ])
            ->latest()
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function statusFilters(): array
    {
        return ['dang_chay' => 'Đang chạy', 'ket_thuc' => 'Đã kết thúc', 'be' => 'Bể', 'tat_ca' => 'Tất cả'];
    }

    public function render(): View
    {
        return view('livewire.groups.group-index');
    }
}
