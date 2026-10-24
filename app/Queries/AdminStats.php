<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\HuiGroupStatus;
use App\Enums\UserRole;
use App\Models\HuiGroup;
use App\Models\User;
use DateTimeImmutable;

/**
 * Số liệu tổng quan cho trang quản trị (document/05-quan-tri.md QT-02). Đọc dữ
 * liệu của mọi tài khoản nên bỏ global scope owner.
 */
final class AdminStats
{
    public const SIGNUP_DAYS = 30;

    /**
     * @return array{users: array{total: int, hui_vien: int, chu_hui: int, locked: int}, signups: array{today: int, week: int, month: int, daily: list<array{date: string, count: int}>}, active: array{week: int, month: int}, groups: array{total: int, byStatus: array<string, int>, byRole: array<string, int>}}
     */
    public function overview(DateTimeImmutable $today): array
    {
        $startOfToday = $today->setTime(0, 0);
        $byRole = User::query()->toBase()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return [
            'users' => [
                'total' => (int) $byRole->sum(),
                UserRole::HuiVien->value => (int) ($byRole[UserRole::HuiVien->value] ?? 0),
                UserRole::ChuHui->value => (int) ($byRole[UserRole::ChuHui->value] ?? 0),
                'locked' => User::query()->whereNotNull('locked_at')->count(),
            ],
            'signups' => [
                'today' => User::query()->where('created_at', '>=', $startOfToday)->count(),
                'week' => User::query()->where('created_at', '>=', $startOfToday->modify('-6 days'))->count(),
                'month' => User::query()->where('created_at', '>=', $startOfToday->modify('-'.(self::SIGNUP_DAYS - 1).' days'))->count(),
                'daily' => $this->dailySignups($startOfToday),
            ],
            'active' => [
                'week' => User::query()->where('last_seen_at', '>=', $startOfToday->modify('-6 days'))->count(),
                'month' => User::query()->where('last_seen_at', '>=', $startOfToday->modify('-'.(self::SIGNUP_DAYS - 1).' days'))->count(),
            ],
            'groups' => $this->groups(),
        ];
    }

    /**
     * Số đăng ký từng ngày trong SIGNUP_DAYS ngày gần nhất, cũ trước, đủ mọi ngày.
     *
     * @return list<array{date: string, count: int}>
     */
    private function dailySignups(DateTimeImmutable $startOfToday): array
    {
        $from = $startOfToday->modify('-'.(self::SIGNUP_DAYS - 1).' days');
        $counts = User::query()->toBase()
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];

        for ($i = 0; $i < self::SIGNUP_DAYS; $i++) {
            $date = $from->modify("+{$i} days")->format('Y-m-d');
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }

    /**
     * @return array{total: int, byStatus: array<string, int>, byRole: array<string, int>}
     */
    private function groups(): array
    {
        $base = fn () => HuiGroup::query()->withoutGlobalScope(HuiGroup::OWNER_SCOPE)->toBase();
        $byStatus = $base()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byRole = $base()
            ->join('users', 'users.id', '=', 'hui_groups.user_id')
            ->selectRaw('users.role as role, count(*) as total')
            ->groupBy('users.role')
            ->pluck('total', 'role');

        return [
            'total' => (int) $byStatus->sum(),
            'byStatus' => $this->countsFor(HuiGroupStatus::cases(), $byStatus->all()),
            'byRole' => $this->countsFor(UserRole::cases(), $byRole->all()),
        ];
    }

    /**
     * Số đếm cho mọi giá trị của enum, kể cả giá trị chưa có dòng nào (= 0).
     *
     * @param  list<HuiGroupStatus|UserRole>  $cases
     * @param  array<string, int|string>  $counts
     * @return array<string, int>
     */
    private function countsFor(array $cases, array $counts): array
    {
        $result = [];

        foreach ($cases as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}
