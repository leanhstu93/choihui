<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\HolidayBreakKind;
use App\Mail\HolidayScheduleReminderMail;
use App\Models\OfficialHolidayBreak;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Từ tháng 10, nhắc quản trị nhập lịch nghỉ Tết năm sau khi chưa có (chạy mỗi thứ Hai qua Scheduler).
 */
#[Signature('holidays:remind-admin {--date= : Ngày chạy (Y-m-d), mặc định hôm nay}')]
#[Description('Nhắc quản trị nhập lịch nghỉ Tết chính thức của năm sau')]
class RemindHolidaySchedule extends Command
{
    public const FROM_MONTH = 10;

    public function handle(): int
    {
        $today = CarbonImmutable::parse($this->option('date') ?: 'today');
        $nextYear = $today->year + 1;
        $admins = config('hui.admin_emails');

        if ($today->month < self::FROM_MONTH || $admins === []) {
            return self::SUCCESS;
        }

        $hasTet = OfficialHolidayBreak::query()->where('year', $nextYear)->where('kind', HolidayBreakKind::TetNguyenDan)->exists();

        if ($hasTet) {
            $this->info("Đã có lịch nghỉ Tết {$nextYear}.");

            return self::SUCCESS;
        }

        Mail::to($admins)->send(new HolidayScheduleReminderMail($nextYear));
        $this->info("Đã nhắc quản trị nhập lịch nghỉ Tết {$nextYear}.");

        return self::SUCCESS;
    }
}
