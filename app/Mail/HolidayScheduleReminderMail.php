<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nhắc quản trị nhập lịch nghỉ Tết, Quốc khánh chính thức của năm sau.
 */
class HolidayScheduleReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public int $year) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nhắc: nhập lịch nghỉ Tết {$this->year} chính thức");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.holiday-schedule-reminder');
    }
}
