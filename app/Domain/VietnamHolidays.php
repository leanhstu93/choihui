<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Ngày lễ trong năm ở Việt Nam, theo dương lịch và âm lịch. PHP thuần.
 *
 * $dayOff là ngày nghỉ lễ theo Bộ luật Lao động; lịch nghỉ Tết cụ thể do Chính phủ công bố hằng năm,
 * ở đây chỉ đánh dấu giao thừa và mùng 1 tới mùng 3.
 */
final class VietnamHolidays
{
    /** @var list<array{int, int, string, string, bool}> ngày, tháng, tên, biểu tượng, nghỉ lễ */
    private const SOLAR = [
        [1, 1, 'Tết Dương lịch', '🎆', true],
        [3, 2, 'Thành lập Đảng Cộng sản Việt Nam', '🚩', false],
        [14, 2, 'Lễ tình nhân', '💝', false],
        [8, 3, 'Quốc tế Phụ nữ', '💐', false],
        [30, 4, 'Giải phóng miền Nam', '🇻🇳', true],
        [1, 5, 'Quốc tế Lao động', '🛠️', true],
        [19, 5, 'Ngày sinh Chủ tịch Hồ Chí Minh', '🌺', false],
        [1, 6, 'Quốc tế Thiếu nhi', '🎈', false],
        [28, 6, 'Ngày Gia đình Việt Nam', '👨‍👩‍👧', false],
        [27, 7, 'Thương binh Liệt sĩ', '🕯️', false],
        [19, 8, 'Cách mạng tháng Tám', '🚩', false],
        [2, 9, 'Quốc khánh', '🇻🇳', true],
        [20, 10, 'Phụ nữ Việt Nam', '🌷', false],
        [20, 11, 'Nhà giáo Việt Nam', '📚', false],
        [22, 12, 'Quân đội Nhân dân Việt Nam', '🎖️', false],
        [25, 12, 'Giáng sinh', '🎄', false],
    ];

    /** @var list<array{int, int, string, string, bool}> */
    private const LUNAR = [
        [1, 1, 'Tết Nguyên đán', '🧧', true],
        [2, 1, 'Mùng 2 Tết', '🧧', true],
        [3, 1, 'Mùng 3 Tết', '🧧', true],
        [15, 1, 'Rằm tháng Giêng', '🌕', false],
        [3, 3, 'Tết Hàn thực', '🍡', false],
        [10, 3, 'Giỗ Tổ Hùng Vương', '🏯', true],
        [15, 4, 'Lễ Phật đản', '🪷', false],
        [5, 5, 'Tết Đoan ngọ', '🍑', false],
        [15, 7, 'Lễ Vu Lan', '🌹', false],
        [15, 8, 'Tết Trung thu', '🥮', false],
        [23, 12, 'Ông Công Ông Táo', '🐟', false],
    ];

    /**
     * Các ngày lễ rơi vào năm dương $year, xếp theo ngày.
     *
     * @return list<array{date: DateTimeImmutable, name: string, icon: string, dayOff: bool, lunar: ?LunarDate}>
     */
    public static function forYear(int $year): array
    {
        $holidays = [];

        foreach (self::SOLAR as [$day, $month, $name, $icon, $dayOff]) {
            $holidays[] = ['date' => new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)), 'name' => $name, 'icon' => $icon, 'dayOff' => $dayOff, 'lunar' => null];
        }

        // Năm dương $year chứa cuối năm âm $year - 1 và phần lớn năm âm $year.
        foreach ([$year - 1, $year] as $lunarYear) {
            foreach (self::LUNAR as [$day, $month, $name, $icon, $dayOff]) {
                $lunar = new LunarDate($day, $month, $lunarYear);
                $holidays[] = ['date' => LunarCalendar::toSolar($lunar), 'name' => $name, 'icon' => $icon, 'dayOff' => $dayOff, 'lunar' => $lunar];
            }

            // Giao thừa: ngày cuối của năm âm, tháng Chạp đủ hay thiếu đều đúng.
            $eve = LunarCalendar::toSolar(new LunarDate(1, 1, $lunarYear + 1))->modify('-1 day');
            $holidays[] = ['date' => $eve, 'name' => 'Giao thừa', 'icon' => '🎇', 'dayOff' => true, 'lunar' => LunarCalendar::fromSolar($eve)];
        }

        $holidays = array_values(array_filter($holidays, fn (array $holiday): bool => (int) $holiday['date']->format('Y') === $year));
        usort($holidays, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $holidays;
    }
}
