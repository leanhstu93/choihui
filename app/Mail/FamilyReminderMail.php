<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\HealthRecord;
use App\Models\User;
use App\Queries\FamilyOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email nhắc ngày nhớ và lịch tái khám (document/09-gia-dinh.md GD-03).
 */
class FamilyReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<FamilyOccurrence>  $events
     * @param  Collection<int, HealthRecord>  $followUps
     */
    public function __construct(
        public User $user,
        public array $events,
        public Collection $followUps,
        public CarbonImmutable $today,
    ) {}

    public function envelope(): Envelope
    {
        $first = $this->events[0] ?? null;
        $subject = $first !== null
            ? "Nhắc: {$first->event->title} ({$this->lowerFirst($first->countdown())})"
            : 'Nhắc lịch tái khám';
        $more = count($this->events) + $this->followUps->count() - 1;

        return new Envelope(subject: $more > 0 ? "{$subject} và {$more} việc khác" : $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.family-reminder');
    }

    private function lowerFirst(string $text): string
    {
        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
