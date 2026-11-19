<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\TaskRepeat;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Đọc một dòng thêm nhanh như "Đóng tiền điện mai 8h !" (document/10-viec-can-lam.md V7). PHP thuần.
 *
 * Bản JS ở resources/js/tasks/quick-parse.js phải theo đúng các quy tắc này (dùng chung bộ ví dụ
 * kiểm thử). Chữ có dấu phải đúng dấu, hoặc gõ hẳn không dấu; riêng "mốt" bắt buộc có dấu vì
 * "mot" trùng với "một". Mỗi loại (lặp, ngày, giờ, quan trọng) chỉ lấy lần khớp đầu tiên, phần
 * còn lại giữ nguyên trong tên. Loại nằm trong $ignore thì không đọc (người dùng bấm bỏ chip).
 */
final class TaskQuickParse
{
    public const string KIND_REPEAT = 'repeat';

    public const string KIND_DATE = 'date';

    public const string KIND_TIME = 'time';

    public const string KIND_IMPORTANT = 'important';

    private const string START = '(?<![\p{L}\p{N}])';

    private const string END = '(?![\p{L}\p{N}])';

    private const string EVERY = '(?:m[oỗ]i|h[aằ]ng)\s+';

    private const string WEEKDAY = 'th[uứ]\s*(?:[2-7]|hai|ba|t[uư]|n[aă]m|s[aá]u|b[aả]y)|ch[uủ]\s*nh[aậ]t|cn';

    private const string PERIOD = '(?<period>s[aá]ng|tr[uư]a|chi[eề]u|t[oố]i|[dđ][eê]m)';

    private const string AT = '(?:(?:v[aà]o\s+)?l[uú]c\s+)?';

    private const string ON = '(?:(?:v[aà]o|ng[aà]y|h[aạ]n)\s+)?';

    /** @var array<int|string, int> Khóa số như "2" được PHP đổi thành int */
    private const array WEEKDAY_WORDS = [
        '2' => 1, 'hai' => 1, '3' => 2, 'ba' => 2, '4' => 3, 'tư' => 3, 'tu' => 3,
        '5' => 4, 'năm' => 4, 'nam' => 4, '6' => 5, 'sáu' => 5, 'sau' => 5, '7' => 6, 'bảy' => 6, 'bay' => 6,
    ];

    /**
     * @param  list<string>  $ignore  Các loại KIND_* không đọc
     */
    public static function parse(string $input, DateTimeImmutable $now, array $ignore = []): ParsedTask
    {
        $today = $now->setTime(0, 0);
        $lower = mb_strtolower($input);
        $taken = [];
        $found = [];

        foreach (self::rules($today) as $kind => $patterns) {
            if (in_array($kind, $ignore, true)) {
                continue;
            }

            foreach ($patterns as [$pattern, $read]) {
                $hit = self::firstMatch($lower, $pattern, $read, $taken);

                if ($hit !== null) {
                    $taken[] = [$hit['start'], $hit['length']];
                    $found[$kind] = $hit;
                    break;
                }
            }
        }

        return self::assemble($input, $today, $found, $taken);
    }

    /**
     * Các mẫu theo loại, loại và mẫu trước được ưu tiên. Mỗi mẫu đi kèm hàm đọc trả về giá trị,
     * hoặc null khi chuỗi khớp nhưng không hợp lệ (31/02, 25h).
     *
     * @return array<string, list<array{0: string, 1: callable(array<string, string>): mixed}>>
     */
    private static function rules(DateTimeImmutable $today): array
    {
        return [
            self::KIND_REPEAT => [
                [
                    '(?:r[aằ]m|m[uùồ]ng\s*(?<day>\d{1,2}))\s+'.self::EVERY.'th[aá]ng',
                    fn (array $m): ?array => self::lunarMonthly(isset($m['day']) && $m['day'] !== '' ? (int) $m['day'] : 15),
                ],
                [
                    self::EVERY.'(?<days>(?:'.self::WEEKDAY.')(?:\s*(?:,|v[aà])\s*(?:'.self::WEEKDAY.'|[2-7]))*)',
                    fn (array $m): array => ['repeat' => TaskRepeat::Weekly, 'weekdays' => self::weekdayMask($m['days'])],
                ],
                [self::EVERY.'ng[aà]y', fn (): array => ['repeat' => TaskRepeat::Daily]],
                [self::EVERY.'tu[aầ]n', fn (): array => ['repeat' => TaskRepeat::Weekly]],
                [self::EVERY.'th[aá]ng', fn (): array => ['repeat' => TaskRepeat::Monthly]],
                [self::EVERY.'n[aă]m', fn (): array => ['repeat' => TaskRepeat::Yearly]],
            ],
            self::KIND_DATE => [
                [self::ON.'h[oô]m\s+nay', fn (): DateTimeImmutable => $today],
                [self::ON.'(?:ng[aà]y\s+)?mai', fn (): DateTimeImmutable => $today->modify('+1 day')],
                [self::ON.'(?:ng[aà]y\s+kia|mốt)', fn (): DateTimeImmutable => $today->modify('+2 day')],
                [self::ON.'cu[oố]i\s+tu[aầ]n', fn (): DateTimeImmutable => self::weekend($today)],
                [
                    self::ON.'(?<wd>'.self::WEEKDAY.')(?<nextweek>\s+tu[aầ]n\s+(?:sau|t[oớ]i))?',
                    fn (array $m): DateTimeImmutable => self::weekday($today, self::isoWeekday($m['wd']), ($m['nextweek'] ?? '') !== ''),
                ],
                [self::ON.'tu[aầ]n\s+(?:sau|t[oớ]i)', fn (): DateTimeImmutable => self::weekday($today, 1, true)],
                [
                    self::ON.'(?<d>\d{1,2})[\/-](?<m>\d{1,2})(?:[\/-](?<y>\d{4}))?',
                    fn (array $m): ?DateTimeImmutable => self::solarDate($today, (int) $m['d'], (int) $m['m'], isset($m['y']) && $m['y'] !== '' ? (int) $m['y'] : null),
                ],
                [
                    self::ON.'(?:r[aằ]m|m[uùồ]ng\s*(?<day>\d{1,2}))(?:\s+th[aá]ng\s+(?<month>\d{1,2}|gi[eê]ng|ch[aạ]p))?',
                    fn (array $m): ?DateTimeImmutable => self::lunarDate($today, $m),
                ],
            ],
            self::KIND_TIME => [
                [
                    self::AT.'(?<h>\d{1,2})\s*(?:h|g|gi[oờ])(?:\s*(?<min>\d{1,2})(?:\s*(?:ph[uú]t|p))?)?(?:\s+'.self::PERIOD.')?',
                    fn (array $m): ?string => self::time((int) $m['h'], isset($m['min']) && $m['min'] !== '' ? (int) $m['min'] : 0, $m['period'] ?? ''),
                ],
                [
                    self::AT.'(?<h>\d{1,2}):(?<min>\d{2})(?:\s+'.self::PERIOD.')?',
                    fn (array $m): ?string => self::time((int) $m['h'], (int) $m['min'], $m['period'] ?? ''),
                ],
            ],
            self::KIND_IMPORTANT => [
                ['quan\s+tr[oọ]ng', fn (): bool => true],
                ['!+', fn (): bool => true],
            ],
        ];
    }

    /**
     * Lần khớp hợp lệ đầu tiên không chồng lên phần đã đọc. Vị trí tính theo ký tự.
     *
     * @param  callable(array<string, string>): mixed  $read
     * @param  list<array{0: int, 1: int}>  $taken
     * @return array{start: int, length: int, value: mixed}|null
     */
    private static function firstMatch(string $lower, string $pattern, callable $read, array $taken): ?array
    {
        $boundary = str_starts_with($pattern, '!') ? '' : self::START;
        $end = str_starts_with($pattern, '!') ? '' : self::END;

        if (preg_match_all('/'.$boundary.'(?:'.$pattern.')'.$end.'/u', $lower, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        foreach ($all as $match) {
            $start = mb_strlen(substr($lower, 0, $match[0][1]));
            $length = mb_strlen($match[0][0]);

            foreach ($taken as [$takenStart, $takenLength]) {
                if ($start < $takenStart + $takenLength && $takenStart < $start + $length) {
                    continue 2;
                }
            }

            $groups = [];

            foreach ($match as $name => $group) {
                if (is_string($name)) {
                    $groups[$name] = $group[1] === -1 ? '' : $group[0];
                }
            }

            $value = $read($groups);

            if ($value !== null) {
                return ['start' => $start, 'length' => $length, 'value' => $value];
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{start: int, length: int, value: mixed}>  $found
     * @param  list<array{0: int, 1: int}>  $taken
     */
    private static function assemble(string $input, DateTimeImmutable $today, array $found, array $taken): ParsedTask
    {
        /** @var array{repeat: TaskRepeat, weekdays?: int, day?: int}|null $repeat */
        $repeat = isset($found[self::KIND_REPEAT]) ? $found[self::KIND_REPEAT]['value'] : null;
        /** @var DateTimeImmutable|null $dueOn */
        $dueOn = isset($found[self::KIND_DATE]) ? $found[self::KIND_DATE]['value'] : null;
        /** @var string|null $dueTime */
        $dueTime = isset($found[self::KIND_TIME]) ? $found[self::KIND_TIME]['value'] : null;

        $repeatKind = $repeat['repeat'] ?? TaskRepeat::None;
        $weekdays = $repeat['weekdays'] ?? 0;
        $repeatDay = $repeat['day'] ?? null;

        if ($repeatKind !== TaskRepeat::None && $dueOn === null) {
            $dueOn = TaskRecurrence::firstOnOrAfter($repeatKind, $today, $weekdays, $repeatDay);
        }

        if ($dueTime !== null && $dueOn === null) {
            $dueOn = $today;
        }

        if ($dueOn !== null) {
            $weekdays = match ($repeatKind) {
                TaskRepeat::Weekly => $weekdays ?: TaskRecurrence::weekdayBit($dueOn),
                default => 0,
            };
            $repeatDay = match ($repeatKind) {
                TaskRepeat::Monthly, TaskRepeat::Yearly => (int) $dueOn->format('j'),
                TaskRepeat::MonthlyLunar => $repeatDay ?? LunarCalendar::fromSolar($dueOn)->day,
                default => null,
            };
        }

        $matches = [];

        foreach ($found as $kind => $hit) {
            $matches[$hit['start']] = ['kind' => $kind, 'text' => mb_substr($input, $hit['start'], $hit['length'])];
        }

        ksort($matches);

        return new ParsedTask(
            title: self::stripSpans($input, $taken),
            dueOn: $dueOn,
            dueTime: $dueTime,
            important: isset($found[self::KIND_IMPORTANT]),
            repeat: $repeatKind,
            weekdays: $weekdays,
            repeatDay: $repeatDay,
            matches: array_values($matches),
        );
    }

    /**
     * @param  list<array{0: int, 1: int}>  $taken
     */
    private static function stripSpans(string $input, array $taken): string
    {
        usort($taken, fn (array $a, array $b): int => $b[0] <=> $a[0]);

        foreach ($taken as [$start, $length]) {
            $input = mb_substr($input, 0, $start).' '.mb_substr($input, $start + $length);
        }

        $title = (string) preg_replace('/\s+/u', ' ', $input);
        $title = (string) preg_replace('/\s+([,.;:])/u', '$1', $title);

        return trim($title, " \t\n\r\0\x0B,.;:-");
    }

    /**
     * @return array{repeat: TaskRepeat, day: int}|null
     */
    private static function lunarMonthly(int $day): ?array
    {
        return $day >= 1 && $day <= 30 ? ['repeat' => TaskRepeat::MonthlyLunar, 'day' => $day] : null;
    }

    private static function weekdayMask(string $days): int
    {
        preg_match_all('/'.self::WEEKDAY.'|[2-7]/u', $days, $items);
        $mask = 0;

        foreach ($items[0] as $item) {
            $mask |= 1 << (self::isoWeekday($item) - 1);
        }

        return $mask;
    }

    /**
     * "thứ 2", "thu hai", "cn", "5"… → 1 (thứ 2) … 7 (chủ nhật).
     */
    private static function isoWeekday(string $text): int
    {
        $text = (string) preg_replace('/^th[uứ]\s*/u', '', $text);

        return self::WEEKDAY_WORDS[$text] ?? 7;
    }

    /**
     * Thứ $iso lần tới, không tính hôm nay; $nextWeek thì lấy thứ đó của tuần sau (tuần bắt đầu thứ 2).
     */
    private static function weekday(DateTimeImmutable $today, int $iso, bool $nextWeek): DateTimeImmutable
    {
        $current = (int) $today->format('N');

        if ($nextWeek) {
            return $today->modify('+'.(7 - $current + $iso).' day');
        }

        $gap = ($iso - $current + 7) % 7;

        return $today->modify('+'.($gap === 0 ? 7 : $gap).' day');
    }

    /**
     * Cuối tuần: hôm nay nếu đang thứ 7 / chủ nhật, không thì thứ 7 tới.
     */
    private static function weekend(DateTimeImmutable $today): DateTimeImmutable
    {
        return (int) $today->format('N') >= 6 ? $today : self::weekday($today, 6, false);
    }

    /**
     * Không ghi năm thì lấy năm nay, ngày đã qua thì sang năm sau.
     */
    private static function solarDate(DateTimeImmutable $today, int $day, int $month, ?int $year): ?DateTimeImmutable
    {
        if ($year !== null) {
            return checkdate($month, $day, $year) ? $today->setDate($year, $month, $day) : null;
        }

        $thisYear = (int) $today->format('Y');

        if (checkdate($month, $day, $thisYear) && $today->setDate($thisYear, $month, $day) >= $today) {
            return $today->setDate($thisYear, $month, $day);
        }

        return checkdate($month, $day, $thisYear + 1) ? $today->setDate($thisYear + 1, $month, $day) : null;
    }

    /**
     * "rằm", "mùng 3" → lần tới của ngày âm đó trong tháng nào cũng được; có tháng ("rằm tháng 7",
     * "mùng 1 tháng giêng") thì lần tới của đúng ngày âm đó. Tính cả hôm nay.
     *
     * @param  array<string, string>  $m
     */
    private static function lunarDate(DateTimeImmutable $today, array $m): ?DateTimeImmutable
    {
        $day = ($m['day'] ?? '') !== '' ? (int) $m['day'] : 15;
        $monthText = $m['month'] ?? '';

        if ($day < 1 || $day > 30) {
            return null;
        }

        if ($monthText === '') {
            return TaskRecurrence::firstOnOrAfter(TaskRepeat::MonthlyLunar, $today, anchorDay: $day);
        }

        $month = match (true) {
            str_starts_with($monthText, 'gi') => 1,
            str_starts_with($monthText, 'ch') => 12,
            default => (int) $monthText,
        };

        try {
            return FamilyCalendar::nextOccurrence(true, $day, $month, $today);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Giờ "HH:MM"; chiều / tối cộng 12, trưa cộng 12 cho 1–3 giờ, đêm cộng 12 cho 6–11 giờ.
     */
    private static function time(int $hour, int $minute, string $period): ?string
    {
        $hour += match (true) {
            in_array($period, ['chiều', 'chieu', 'tối', 'toi'], true) && $hour < 12 => 12,
            in_array($period, ['trưa', 'trua'], true) && $hour >= 1 && $hour <= 3 => 12,
            in_array($period, ['đêm', 'dem'], true) && $hour >= 6 && $hour < 12 => 12,
            default => 0,
        };

        return $hour <= 23 && $minute <= 59 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }
}
