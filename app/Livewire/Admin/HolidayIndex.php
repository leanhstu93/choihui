<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\DeleteOfficialHolidayBreak;
use App\Actions\Admin\SaveOfficialHolidayBreak;
use App\Domain\HolidayBreak;
use App\Enums\HolidayBreakKind;
use App\Http\Requests\SaveHolidayBreakRequest;
use App\Livewire\Concerns\AdminOnly;
use App\Models\OfficialHolidayBreak;
use App\Queries\HolidaySchedule;
use App\Queries\YearCalendar;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Nhập lịch nghỉ lễ chính thức khi Chính phủ công bố. Đợt chưa nhập dùng lịch dự kiến tự tính.
 */
#[Layout('layouts.app')]
#[Title('Lịch nghỉ lễ · Quản trị')]
class HolidayIndex extends Component
{
    use AdminOnly;

    public int $year;

    /** Đợt đang sửa (giá trị HolidayBreakKind). */
    public ?string $editing = null;

    /** @var array{starts_on: string, ends_on: string, make_up_on: string, source: string} */
    public array $form = ['starts_on' => '', 'ends_on' => '', 'make_up_on' => '', 'source' => ''];

    public function mount(): void
    {
        $this->year = CarbonImmutable::today()->year;
    }

    public function selectYear(int $year): void
    {
        abort_unless(YearCalendar::supports($year), 404);
        $this->year = $year;
        $this->cancel();
    }

    public function edit(string $kind, HolidaySchedule $schedule): void
    {
        $break = collect($schedule->forYear($this->year))->first(fn (HolidayBreak $break): bool => $break->kind->value === $kind);
        abort_if($break === null, 404);

        $this->editing = $kind;
        $this->form = [
            'starts_on' => $break->start->format('Y-m-d'),
            'ends_on' => $break->end->format('Y-m-d'),
            'make_up_on' => $break->makeUpOn?->format('Y-m-d') ?? '',
            'source' => $break->source ?? (string) OfficialHolidayBreak::query()->where('year', $this->year)->whereNotNull('source')->value('source'),
        ];
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetValidation();
    }

    public function save(SaveOfficialHolidayBreak $saveBreak): void
    {
        $validated = SaveHolidayBreakRequest::makeValidator(['year' => $this->year, 'kind' => $this->editing] + $this->form)->validate();

        $saveBreak->handle($this->admin(), $validated);
        $this->editing = null;
    }

    public function revert(string $kind, DeleteOfficialHolidayBreak $deleteBreak): void
    {
        $deleteBreak->handle($this->admin(), $this->year, HolidayBreakKind::from($kind));
        $this->cancel();
    }

    public function render(HolidaySchedule $schedule): View
    {
        $thisYear = CarbonImmutable::today()->year;

        return view('livewire.admin.holiday-index', [
            'breaks' => $schedule->forYear($this->year),
            'years' => [$thisYear, $thisYear + 1, $thisYear + 2],
        ]);
    }
}
