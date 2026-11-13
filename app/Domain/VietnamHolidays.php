<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\HolidayBreakKind;
use DateTimeImmutable;

/**
 * Ngày lễ trong năm ở Việt Nam, theo dương lịch và âm lịch. PHP thuần.
 *
 * $dayOff là ngày nghỉ lễ theo Bộ luật Lao động (và 24/11 từ 2026); lịch nghỉ Tết cụ thể do Chính phủ công bố hằng năm,
 * ở đây chỉ đánh dấu giao thừa và mùng 1 tới mùng 3.
 */
final class VietnamHolidays
{
    /** @var list<array{int, int, string, string, bool, string}> ngày, tháng, tên, biểu tượng, nghỉ lễ, đôi dòng giới thiệu */
    private const SOLAR = [
        [1, 1, 'Tết Dương lịch', '🎆', true, 'Ngày đầu năm mới theo dương lịch. Người lao động được nghỉ 1 ngày.'],
        [3, 2, 'Thành lập Đảng Cộng sản Việt Nam', '🚩', false, 'Kỷ niệm ngày thành lập Đảng Cộng sản Việt Nam năm 1930.'],
        [14, 2, 'Lễ tình nhân', '💝', false, 'Ngày Valentine, dịp các đôi tặng nhau hoa, sô-cô-la và quà.'],
        [8, 3, 'Quốc tế Phụ nữ', '💐', false, 'Dịp tặng hoa, tặng quà cho mẹ, vợ, chị em và đồng nghiệp nữ.'],
        [30, 4, 'Giải phóng miền Nam', '🇻🇳', true, 'Kỷ niệm ngày giải phóng miền Nam, thống nhất đất nước năm 1975. Nghỉ 1 ngày.'],
        [1, 5, 'Quốc tế Lao động', '🛠️', true, 'Ngày Quốc tế Lao động, nghỉ 1 ngày và thường nghỉ liền với 30/4.'],
        [19, 5, 'Ngày sinh Chủ tịch Hồ Chí Minh', '🌺', false, 'Kỷ niệm ngày sinh Chủ tịch Hồ Chí Minh năm 1890.'],
        [1, 6, 'Quốc tế Thiếu nhi', '🎈', false, 'Ngày của các bé: vui chơi, đi công viên, nhận quà.'],
        [28, 6, 'Ngày Gia đình Việt Nam', '👨‍👩‍👧', false, 'Dịp cả nhà quây quần, sum họp và dành thời gian cho nhau.'],
        [27, 7, 'Thương binh Liệt sĩ', '🕯️', false, 'Tưởng nhớ những người đã hy sinh vì Tổ quốc, thăm hỏi gia đình thương binh, liệt sĩ.'],
        [19, 8, 'Cách mạng tháng Tám', '🚩', false, 'Kỷ niệm Cách mạng tháng Tám thành công năm 1945.'],
        [2, 9, 'Quốc khánh', '🇻🇳', true, 'Kỷ niệm ngày đọc Tuyên ngôn Độc lập năm 1945. Nghỉ 2 ngày: 2/9 và một ngày liền trước hoặc sau.'],
        [20, 10, 'Phụ nữ Việt Nam', '🌷', false, 'Kỷ niệm ngày thành lập Hội Liên hiệp Phụ nữ Việt Nam năm 1930, dịp tặng hoa cho phái nữ.'],
        [20, 11, 'Nhà giáo Việt Nam', '📚', false, 'Dịp tri ân thầy cô, học trò cũ về thăm trường.'],
        [22, 12, 'Quân đội Nhân dân Việt Nam', '🎖️', false, 'Kỷ niệm ngày thành lập Quân đội Nhân dân Việt Nam năm 1944.'],
        [25, 12, 'Giáng sinh', '🎄', false, 'Lễ Noel. Đêm 24/12 phố xá trang trí đèn, nhiều nhà thờ có lễ đêm.'],
    ];

    /** @var list<array{int, int, string, string, bool, string}> */
    private const LUNAR = [
        [1, 1, 'Tết Nguyên đán', '🧧', true, 'Tết cổ truyền lớn nhất năm: cúng tổ tiên, chúc Tết, mừng tuổi, xông đất.'],
        [2, 1, 'Mùng 2 Tết', '🧧', true, '"Mùng 1 Tết cha, mùng 2 Tết mẹ": ngày thường đi chúc Tết bên ngoại.'],
        [3, 1, 'Mùng 3 Tết', '🧧', true, '"Mùng 3 Tết thầy": ngày thăm thầy cô. Nhiều nhà làm lễ hóa vàng.'],
        [15, 1, 'Rằm tháng Giêng', '🌕', false, 'Tết Nguyên tiêu, rằm lớn đầu năm. Nhiều nhà cúng rằm, đi chùa cầu an.'],
        [3, 3, 'Tết Hàn thực', '🍡', false, 'Tết bánh trôi, bánh chay, dâng tổ tiên những viên bánh tròn trịa.'],
        [10, 3, 'Giỗ Tổ Hùng Vương', '🏯', true, 'Tưởng nhớ các Vua Hùng có công dựng nước. Nghỉ 1 ngày.'],
        [15, 4, 'Lễ Phật đản', '🪷', false, 'Kỷ niệm ngày Đức Phật ra đời. Chùa treo cờ, làm lễ tắm Phật.'],
        [5, 5, 'Tết Đoan ngọ', '🍑', false, 'Tết diệt sâu bọ: sáng sớm ăn hoa quả chua, rượu nếp, bánh tro.'],
        [15, 7, 'Lễ Vu Lan', '🌹', false, 'Lễ báo hiếu cha mẹ, cài hoa hồng trên áo. Rằm tháng Bảy cũng là ngày cúng cô hồn.'],
        [15, 8, 'Tết Trung thu', '🥮', false, 'Tết của thiếu nhi: rước đèn, phá cỗ, ăn bánh trung thu, ngắm trăng.'],
        [23, 12, 'Ông Công Ông Táo', '🐟', false, 'Cúng tiễn ông Công ông Táo về trời, thả cá chép phóng sinh.'],
    ];

    /**
     * Các ngày lễ rơi vào năm dương $year, xếp theo ngày.
     *
     * @return list<array{date: DateTimeImmutable, name: string, icon: string, dayOff: bool, lunar: ?LunarDate, about: string}>
     */
    public static function forYear(int $year): array
    {
        $holidays = [];

        foreach (self::SOLAR as [$day, $month, $name, $icon, $dayOff, $about]) {
            $holidays[] = ['date' => new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)), 'name' => $name, 'icon' => $icon, 'dayOff' => $dayOff, 'lunar' => null, 'about' => $about];
        }

        // Nghị quyết 28/2026/QH16: 24/11 hằng năm là Ngày Văn hóa Việt Nam, người lao động được nghỉ.
        if ($year >= HolidayBreakKind::VanHoa->sinceYear()) {
            $holidays[] = ['date' => new DateTimeImmutable("{$year}-11-24"), 'name' => 'Ngày Văn hóa Việt Nam', 'icon' => '🎭', 'dayOff' => true, 'lunar' => null, 'about' => 'Ngày tôn vinh văn hóa Việt Nam. Từ năm 2026 người lao động được nghỉ làm, hưởng nguyên lương.'];
        }

        // Năm dương $year chứa cuối năm âm $year - 1 và phần lớn năm âm $year.
        foreach ([$year - 1, $year] as $lunarYear) {
            foreach (self::LUNAR as [$day, $month, $name, $icon, $dayOff, $about]) {
                $lunar = new LunarDate($day, $month, $lunarYear);
                $holidays[] = ['date' => LunarCalendar::toSolar($lunar), 'name' => $name, 'icon' => $icon, 'dayOff' => $dayOff, 'lunar' => $lunar, 'about' => $about];
            }

            // Giao thừa: ngày cuối của năm âm, tháng Chạp đủ hay thiếu đều đúng.
            $eve = LunarCalendar::toSolar(new LunarDate(1, 1, $lunarYear + 1))->modify('-1 day');
            $holidays[] = ['date' => $eve, 'name' => 'Giao thừa', 'icon' => '🎇', 'dayOff' => true, 'lunar' => LunarCalendar::fromSolar($eve), 'about' => 'Thời khắc chuyển sang năm mới. Nhà nhà cúng giao thừa, đón năm mới.'];
        }

        $holidays = array_values(array_filter($holidays, fn (array $holiday): bool => (int) $holiday['date']->format('Y') === $year));
        usort($holidays, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $holidays;
    }

    /**
     * Ngày lễ rơi vào đúng ngày $date.
     *
     * @return list<array{date: DateTimeImmutable, name: string, icon: string, dayOff: bool, lunar: ?LunarDate, about: string}>
     */
    public static function on(DateTimeImmutable $date): array
    {
        $day = $date->format('Y-m-d');

        return array_values(array_filter(self::forYear((int) $date->format('Y')), fn (array $holiday): bool => $holiday['date']->format('Y-m-d') === $day));
    }
}
