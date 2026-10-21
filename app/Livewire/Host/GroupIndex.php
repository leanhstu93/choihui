<?php

declare(strict_types=1);

namespace App\Livewire\Host;

use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Enums\HuiGroupStatus;
use App\Models\HuiGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Danh sách dây chủ hụi, lọc theo trạng thái (document/04-chu-hui.md mục 7).
 */
#[Layout('layouts.app')]
#[Title('Dây hụi')]
class GroupIndex extends Component
{
    #[Url(as: 'trang-thai')]
    public string $status = 'dang_chay';

    /**
     * @return Collection<int, HuiGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return HuiGroup::query()
            ->when(HuiGroupStatus::tryFrom($this->status), fn (Builder $query, HuiGroupStatus $status) => $query->where('status', $status))
            ->withCount([
                'rounds as recorded_rounds_count' => fn (Builder $query) => $query->whereNotNull('winner_host_slot_id'),
                'hostEntries as uncollected_count' => fn (Builder $query) => $query
                    ->where('direction', HostDirection::Thu)
                    ->where('host_entries.status', CashEntryStatus::Chua),
                'hostEntries as unpaid_out_count' => fn (Builder $query) => $query
                    ->where('direction', HostDirection::Chi)
                    ->where('host_entries.status', CashEntryStatus::Chua),
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
        return view('livewire.host.group-index');
    }
}
