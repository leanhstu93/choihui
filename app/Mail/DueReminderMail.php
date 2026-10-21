<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Queries\UpcomingPayment;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email nhắc các khoản hụi sắp đến hạn (FR-09).
 */
class DueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<UpcomingPayment>  $payments
     */
    public function __construct(
        public User $user,
        public array $payments,
        public DateTimeImmutable $today,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nhắc đóng hụi: '.number_format($this->total(), 0, ',', '.').' đ sắp đến hạn',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.due-reminder',
            with: [
                'total' => $this->total(),
                'hasEstimate' => array_filter($this->payments, fn (UpcomingPayment $payment) => $payment->isEstimate) !== [],
            ],
        );
    }

    private function total(): int
    {
        return array_sum(array_map(fn (UpcomingPayment $payment) => $payment->amount, $this->payments));
    }
}
