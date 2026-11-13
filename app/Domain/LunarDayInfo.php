<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Thông tin một ngày kiểu lịch vạn niên: ngày âm, can chi, tiết khí, ngày hoàng đạo/hắc đạo,
 * giờ hoàng đạo, pha mặt trăng. Ngày tốt xấu chỉ để tham khảo. PHP thuần.
 */
final readonly class LunarDayInfo
{
    public const SOLAR_TERMS = [
        'Xuân phân', 'Thanh minh', 'Cốc vũ', 'Lập hạ', 'Tiểu mãn', 'Mang chủng',
        'Hạ chí', 'Tiểu thử', 'Đại thử', 'Lập thu', 'Xử thử', 'Bạch lộ',
        'Thu phân', 'Hàn lộ', 'Sương giáng', 'Lập đông', 'Tiểu tuyết', 'Đại tuyết',
        'Đông chí', 'Tiểu hàn', 'Đại hàn', 'Lập xuân', 'Vũ thủy', 'Kinh trập',
    ];

    /** @var list<array{string, bool}> 12 sao trực ngày: tên, có phải hoàng đạo */
    public const STARS = [
        ['Thanh Long', true], ['Minh Đường', true], ['Thiên Hình', false], ['Chu Tước', false],
        ['Kim Quỹ', true], ['Kim Đường', true], ['Bạch Hổ', false], ['Ngọc Đường', true],
        ['Thiên Lao', false], ['Nguyên Vũ', false], ['Tư Mệnh', true], ['Câu Trận', false],
    ];

    /** @var list<array{string, string}> biểu tượng, tên */
    public const MOON_PHASES = [
        ['🌑', 'Trăng non'], ['🌒', 'Trăng lưỡi liềm đầu tháng'], ['🌓', 'Trăng thượng huyền'], ['🌔', 'Trăng gần tròn'],
        ['🌕', 'Trăng tròn'], ['🌖', 'Trăng bắt đầu khuyết'], ['🌗', 'Trăng hạ huyền'], ['🌘', 'Trăng lưỡi liềm cuối tháng'],
    ];

    /**
     * @param  list<int>  $luckyHours  chi của các giờ hoàng đạo
     */
    public function __construct(
        public DateTimeImmutable $date,
        public LunarDate $lunar,
        public int $dayCan,
        public int $dayChi,
        public int $solarTerm,
        public bool $solarTermStarts,
        public int $star,
        public array $luckyHours,
        public int $moonPhase,
    ) {}

    public static function of(DateTimeImmutable $date): self
    {
        $date = $date->setTime(0, 0);
        $lunar = LunarCalendar::fromSolar($date);
        $julianDay = LunarCalendar::julianDayOf($date);
        $dayChi = CanChi::dayChi($julianDay);
        $solarTerm = LunarCalendar::solarTerm($date);

        return new self(
            date: $date,
            lunar: $lunar,
            dayCan: CanChi::dayCan($julianDay),
            dayChi: $dayChi,
            solarTerm: $solarTerm,
            solarTermStarts: LunarCalendar::solarTerm($date->modify('-1 day')) !== $solarTerm,
            star: self::starOf($lunar->month, $dayChi),
            luckyHours: CanChi::luckyHours($dayChi),
            moonPhase: self::moonPhaseOf($lunar->day),
        );
    }

    /**
     * Sao Thanh Long rơi vào ngày Tý ở tháng 1 và 7, ngày Dần ở tháng 2 và 8, … rồi lần lượt 12 sao.
     */
    public static function starOf(int $lunarMonth, int $dayChi): int
    {
        $start = (($lunarMonth - 1) % 6) * 2;

        return ($dayChi - $start + 12) % 12;
    }

    public static function moonPhaseOf(int $lunarDay): int
    {
        return match (true) {
            $lunarDay === 1 => 0,
            $lunarDay <= 6 => 1,
            $lunarDay <= 8 => 2,
            $lunarDay <= 13 => 3,
            $lunarDay <= 16 => 4,
            $lunarDay <= 21 => 5,
            $lunarDay <= 23 => 6,
            default => 7,
        };
    }

    public function dayName(): string
    {
        return CanChi::CAN[$this->dayCan].' '.CanChi::CHI[$this->dayChi];
    }

    public function monthName(): string
    {
        return CanChi::monthName($this->lunar->month, $this->lunar->year);
    }

    public function yearName(): string
    {
        return CanChi::yearName($this->lunar->year);
    }

    public function solarTermName(): string
    {
        return self::SOLAR_TERMS[$this->solarTerm];
    }

    public function starName(): string
    {
        return self::STARS[$this->star][0];
    }

    public function isGoodDay(): bool
    {
        return self::STARS[$this->star][1];
    }

    /**
     * Ví dụ ["Tý (23–1h)", "Sửu (1–3h)", …].
     *
     * @return list<string>
     */
    public function luckyHourLabels(): array
    {
        return array_map(fn (int $hour): string => CanChi::CHI[$hour].' ('.CanChi::hourRange($hour).')', $this->luckyHours);
    }

    /**
     * @return array{string, string}
     */
    public function moon(): array
    {
        return self::MOON_PHASES[$this->moonPhase];
    }

    /**
     * Gọn cho trình duyệt: [ngày âm, tháng âm, nhuận, năm âm, can ngày, chi ngày, can tháng, chi tháng,
     * tiết khí, bắt đầu tiết, sao, pha trăng].
     *
     * @return array{int, int, int, int, int, int, int, int, int, int, int, int}
     */
    public function toCompact(): array
    {
        return [
            $this->lunar->day, $this->lunar->month, $this->lunar->isLeap ? 1 : 0, $this->lunar->year,
            $this->dayCan, $this->dayChi, CanChi::monthCan($this->lunar->month, $this->lunar->year), CanChi::monthChi($this->lunar->month),
            $this->solarTerm, $this->solarTermStarts ? 1 : 0, $this->star, $this->moonPhase,
        ];
    }

    /**
     * Bảng tên để trình duyệt đọc dạng gọn của toCompact().
     *
     * @return array{can: list<string>, chi: list<string>, chiIcons: list<string>, terms: list<string>, stars: list<array{string, bool}>, moons: list<array{string, string}>, hours: list<string>, luckyHours: list<list<int>>}
     */
    public static function dictionary(): array
    {
        return [
            'can' => CanChi::CAN,
            'chi' => CanChi::CHI,
            'chiIcons' => CanChi::CHI_ICON,
            'terms' => self::SOLAR_TERMS,
            'stars' => self::STARS,
            'moons' => self::MOON_PHASES,
            'hours' => array_map(CanChi::hourRange(...), range(0, 11)),
            'luckyHours' => array_map(CanChi::luckyHours(...), range(0, 11)),
        ];
    }
}
