<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\FamilyCalendar;
use App\Models\FamilyEvent;
use App\Models\HealthRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ngày nhớ và lịch tái khám theo ngày (document/09-gia-dinh.md). Lọc user_id tường minh và bỏ
 * global scope, vì lệnh nhắc chạy nền không có ai đăng nhập.
 */
final class FamilyAgenda
{
    /**
     * Số ngày nhắc trước lịch tái khám (G10).
     */
    public const FOLLOW_UP_DAYS_BEFORE = 1;

    /**
     * Mọi ngày nhớ, lần tới gần nhất lên đầu.
     *
     * @return list<FamilyOccurrence>
     */
    public function upcoming(User $user, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();

        $occurrences = FamilyEvent::query()
            ->withoutGlobalScope(FamilyEvent::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (FamilyEvent $event) => $this->occurrenceOf($event, $today))
            ->all();

        usort($occurrences, fn (FamilyOccurrence $a, FamilyOccurrence $b) => [$a->daysLeft, $a->event->title] <=> [$b->daysLeft, $b->event->title]);

        return $occurrences;
    }

    /**
     * Những gì cần nhắc trong ngày $today: ngày nhớ còn đúng N ngày hoặc đúng hôm nay (G6),
     * lịch tái khám ngày mai hoặc hôm nay (G10).
     *
     * @return array{events: list<FamilyOccurrence>, followUps: Collection<int, HealthRecord>}
     */
    public function remindersFor(User $user, CarbonImmutable $today): array
    {
        $events = array_values(array_filter(
            $this->upcoming($user, $today),
            fn (FamilyOccurrence $occurrence) => $occurrence->event->remind_days_before > 0
                && in_array($occurrence->daysLeft, [0, $occurrence->event->remind_days_before], true),
        ));

        return ['events' => $events, 'followUps' => $this->followUps($user, $today, $today->addDays(self::FOLLOW_UP_DAYS_BEFORE))];
    }

    /**
     * Lịch tái khám từ $from tới $until (cả hai đầu), sớm nhất lên đầu.
     *
     * @return Collection<int, HealthRecord>
     */
    public function followUps(User $user, CarbonImmutable $from, ?CarbonImmutable $until = null): Collection
    {
        return HealthRecord::query()
            ->withoutGlobalScope(HealthRecord::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->where('follow_up_on', '>=', $from->toDateString())
            ->when($until, fn ($query) => $query->where('follow_up_on', '<=', $until->toDateString()))
            ->with(['person' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderBy('follow_up_on')
            ->orderBy('id')
            ->get();
    }

    public function occurrenceOf(FamilyEvent $event, CarbonImmutable $today): FamilyOccurrence
    {
        $date = $event->nextOccurrence($today);

        return new FamilyOccurrence(
            $event,
            $date,
            FamilyCalendar::daysBetween($today, $date),
            FamilyEvent::lunarLabel($date),
            $event->yearsLabel($date),
        );
    }
}
