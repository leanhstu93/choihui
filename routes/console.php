<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * Cron trên server chạy `php artisan schedule:run` mỗi phút (document/02-kien-truc.md mục 9).
 */
Schedule::command('hui:send-due-reminders')
    ->dailyAt('07:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();
