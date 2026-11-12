<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\HealthPerson;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Dữ liệu các màn sổ sức khỏe (document/09-gia-dinh.md GD-05, GD-07). Lọc user_id tường minh.
 */
final class HealthBook
{
    public function __construct(private FamilyAgenda $agenda) {}

    /**
     * @return array{people: Collection<int, HealthPerson>, followUps: Collection<int, HealthRecord>, yearTotal: int, year: int}
     */
    public function forUser(User $user): array
    {
        $today = now()->toImmutable()->startOfDay();

        return [
            'people' => HealthPerson::query()
                ->where('user_id', $user->id)
                ->withCount('records')
                ->withMax('records', 'visited_on')
                ->orderByDesc('records_max_visited_on')
                ->orderBy('name')
                ->get(),
            'followUps' => $this->agenda->followUps($user, $today),
            'yearTotal' => (int) HealthRecord::query()
                ->where('user_id', $user->id)
                ->whereBetween('visited_on', [$today->startOfYear()->toDateString(), $today->endOfYear()->toDateString()])
                ->sum('amount'),
            'year' => $today->year,
        ];
    }

    /**
     * Lịch sử một người, lần mới nhất lên đầu.
     *
     * @return array{records: Collection<int, HealthRecord>, total: int, followUps: Collection<int, HealthRecord>}
     */
    public function forPerson(HealthPerson $person): array
    {
        $today = now()->toImmutable()->startOfDay()->toDateString();
        $records = $person->records()->with('photos')->orderByDesc('visited_on')->orderByDesc('id')->get();

        return [
            'records' => $records,
            'total' => (int) $records->sum('amount'),
            'followUps' => $records
                ->filter(fn (HealthRecord $record) => $record->follow_up_on !== null && $record->follow_up_on->toDateString() >= $today)
                ->sortBy(fn (HealthRecord $record) => $record->follow_up_on->toDateString())
                ->values(),
        ];
    }
}
