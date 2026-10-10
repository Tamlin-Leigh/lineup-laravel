<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Every booking update is emailed and also stored for the in-app Bookings page. Sent from the
 * queue so an owner booking several bands isn't kept waiting on the mail server.
 */
abstract class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry a few times, e.g. when the mail provider rate limits a burst of requests. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public BookingRequest $booking)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * One line summing up the update, shown in-app.
     */
    abstract protected function summary(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_request_id' => $this->booking->id,
            'event_id' => $this->booking->event_id,
            'summary' => $this->summary(),
        ];
    }

    protected function when(): string
    {
        return $this->booking->event->starts_at->format('D j M Y \a\t H:i');
    }

    /**
     * Was a venue asked to host, rather than a band asked to play?
     */
    protected function forVenue(): bool
    {
        return $this->booking->recipient->role === UserRole::Venue;
    }

    protected function ownerName(): string
    {
        return $this->booking->event->owner->displayName();
    }

    protected function recipientName(): string
    {
        return $this->booking->recipient->displayName();
    }
}
