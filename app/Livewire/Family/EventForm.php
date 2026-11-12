<?php

declare(strict_types=1);

namespace App\Livewire\Family;

use App\Actions\Family\DeleteFamilyEvent;
use App\Actions\Family\SaveFamilyEvent;
use App\Domain\FamilyCalendar;
use App\Enums\CalendarType;
use App\Enums\FamilyEventKind;
use App\Http\Requests\StoreFamilyEventRequest;
use App\Models\FamilyEvent;
use App\Queries\FamilyAgenda;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Thêm, sửa, xóa một ngày nhớ (document/09-gia-dinh.md GD-01).
 */
#[Layout('layouts.app')]
#[Title('Ngày nhớ')]
class EventForm extends Component
{
    #[Locked]
    public ?string $eventId = null;

    public string $title = '';

    public string $kind = 'gio';

    public string $calendar = 'am';

    public string $day = '';

    public string $month = '';

    public string $originYear = '';

    public string $remindDaysBefore = '3';

    public string $note = '';

    public function mount(?FamilyEvent $familyEvent = null): void
    {
        if ($familyEvent?->exists) {
            $this->authorize('update', $familyEvent);
            $this->fill([
                'eventId' => $familyEvent->id,
                'title' => $familyEvent->title,
                'kind' => $familyEvent->kind->value,
                'calendar' => $familyEvent->calendar->value,
                'day' => (string) $familyEvent->day,
                'month' => (string) $familyEvent->month,
                'originYear' => (string) $familyEvent->origin_year,
                'remindDaysBefore' => (string) $familyEvent->remind_days_before,
                'note' => (string) $familyEvent->note,
            ]);
        }
    }

    public function save(SaveFamilyEvent $saveFamilyEvent): void
    {
        $event = $this->eventId === null ? null : FamilyEvent::query()->findOrFail($this->eventId);

        if ($event !== null) {
            $this->authorize('update', $event);
        }

        $validated = StoreFamilyEventRequest::makeValidator([
            'title' => trim($this->title),
            'kind' => $this->kind,
            'calendar' => $this->calendar,
            'day' => self::toInt($this->day),
            'month' => self::toInt($this->month),
            'origin_year' => self::toInt($this->originYear),
            'remind_days_before' => self::toInt($this->remindDaysBefore),
            'note' => trim($this->note) ?: null,
        ])->validate();

        $saveFamilyEvent->handle($event, $validated);

        $this->redirectRoute('family.index', navigate: true);
    }

    public function delete(DeleteFamilyEvent $deleteFamilyEvent): void
    {
        $event = FamilyEvent::query()->findOrFail($this->eventId);
        $this->authorize('delete', $event);
        $deleteFamilyEvent->handle($event);

        $this->redirectRoute('family.index', navigate: true);
    }

    /**
     * Ô nhập để trống thành null, số thành int; chữ giữ nguyên để validate báo lỗi.
     */
    private static function toInt(string $value): int|string|null
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : $value;
    }

    public function render(FamilyAgenda $agenda): View
    {
        $lunar = $this->calendar === CalendarType::Am->value;
        $preview = null;

        $day = self::toInt($this->day);
        $month = self::toInt($this->month);

        if (is_int($day) && is_int($month) && FamilyCalendar::isValid($lunar, $day, $month)) {
            $draft = new FamilyEvent([
                'kind' => FamilyEventKind::tryFrom($this->kind) ?? FamilyEventKind::Khac,
                'calendar' => $lunar ? CalendarType::Am : CalendarType::Duong,
                'day' => $day,
                'month' => $month,
                'origin_year' => is_int($year = self::toInt($this->originYear)) ? $year : null,
            ]);
            $preview = $agenda->occurrenceOf($draft, now()->toImmutable()->startOfDay());
        }

        return view('livewire.family.event-form', [
            'kinds' => FamilyEventKind::cases(),
            'maxDay' => $lunar ? 30 : 31,
            'preview' => $preview,
        ]);
    }
}
