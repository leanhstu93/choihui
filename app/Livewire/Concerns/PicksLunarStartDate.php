<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\LunarCalendar;
use App\Domain\LunarDate;
use App\Enums\HuiFrequency;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Chọn lịch chơi của dây khi tạo: dương lịch dùng ô ngày, âm lịch chọn ngày /
 * tháng / năm âm rồi đổi ra ngày dương để lưu vào start_date.
 *
 * Tháng là chuỗi để phân biệt tháng nhuận: "5" và "5n".
 */
trait PicksLunarStartDate
{
    public string $frequency = 'thang';

    public ?int $lunarDay = null;

    public string $lunarMonth = '1';

    public ?int $lunarYear = null;

    private function fillLunarDefaults(): void
    {
        $today = LunarCalendar::fromSolar(now()->toDateTimeImmutable());

        $this->lunarDay = $today->day;
        $this->lunarMonth = $today->month.($today->isLeap ? 'n' : '');
        $this->lunarYear = $today->year;
    }

    /**
     * Đổi năm có thể làm mất tháng nhuận đang chọn: về tháng thường cùng số.
     */
    public function updatedLunarYear(): void
    {
        if (! array_key_exists($this->lunarMonth, $this->lunarMonthOptions())) {
            $this->lunarMonth = rtrim($this->lunarMonth, 'n');
        }
    }

    /**
     * @return array<int|string, string> giá trị tháng => nhãn
     */
    public function lunarMonthOptions(): array
    {
        $leapMonth = $this->lunarYearIsValid() ? LunarCalendar::leapMonth((int) $this->lunarYear) : null;
        $options = [];

        foreach (range(1, 12) as $month) {
            $options[(string) $month] = "Tháng {$month}";

            if ($month === $leapMonth) {
                $options["{$month}n"] = "Tháng {$month} nhuận";
            }
        }

        return $options;
    }

    /**
     * Ngày dương tương ứng để người dùng đối chiếu, null khi ngày âm chưa hợp lệ.
     */
    public function lunarSolarPreview(): ?string
    {
        try {
            return LunarCalendar::toSolar($this->selectedLunarDate())->format('d/m/Y');
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * start_date dạng Y-m-d để lưu, theo lịch đang chọn.
     *
     * @throws ValidationException
     */
    private function resolvedStartDate(): string
    {
        if ($this->frequency !== HuiFrequency::ThangAm->value) {
            return $this->startDate;
        }

        try {
            return LunarCalendar::toSolar($this->selectedLunarDate())->format('Y-m-d');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['start_date' => $exception->getMessage()]);
        }
    }

    private function selectedLunarDate(): LunarDate
    {
        if (! $this->lunarYearIsValid()) {
            throw new InvalidArgumentException('Năm âm lịch phải từ 1990 đến 2100.');
        }

        return new LunarDate(
            (int) $this->lunarDay,
            (int) $this->lunarMonth,
            (int) $this->lunarYear,
            str_ends_with($this->lunarMonth, 'n'),
        );
    }

    private function lunarYearIsValid(): bool
    {
        return $this->lunarYear !== null && $this->lunarYear >= 1990 && $this->lunarYear <= 2100;
    }
}
