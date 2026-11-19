<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\HealthRecord;
use App\Models\Task;
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
 * Email buổi sáng: ngày nhớ, lịch tái khám (document/09-gia-dinh.md GD-03) và việc quá hạn / hôm nay
 * (document/10-viec-can-lam.md V14).
 */
class FamilyReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Số việc liệt kê tối đa trong email; phần còn lại ghi "và N việc khác".
     */
    public const int MAX_TASKS = 10;

    /**
     * @param  list<FamilyOccurrence>  $events
     * @param  Collection<int, HealthRecord>  $followUps
     * @param  list<Task>  $tasks  Việc quá hạn và hôm nay, quá hạn lên đầu
     */
    public function __construct(
        public User $user,
        public array $events,
        public Collection $followUps,
        public CarbonImmutable $today,
        public array $tasks = [],
    ) {}

    public function envelope(): Envelope
    {
        $first = $this->events[0] ?? null;
        $subject = match (true) {
            $first !== null => "Nhắc: {$first->event->title} ({$this->lowerFirst($first->countdown())})",
            $this->followUps->isNotEmpty() => 'Nhắc lịch tái khám',
            default => "Việc hôm nay: {$this->tasks[0]->title}",
        };
        $more = count($this->events) + $this->followUps->count() + count($this->tasks) - 1;

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
