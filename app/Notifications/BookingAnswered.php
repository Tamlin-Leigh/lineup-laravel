<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the event's owner when a band or venue accepts or declines.
 */
class BookingAnswered extends BookingNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $replacements = ['name' => $this->recipientName(), 'event' => $this->booking->event->title, 'when' => $this->when()];

        $line = match (true) {
            $this->accepted() && $this->forVenue() => __(':name will host **:event** on :when.', $replacements),
            $this->accepted() => __(':name will play at **:event** on :when.', $replacements),
            $this->forVenue() => __(':name can\'t host **:event** on :when.', $replacements),
            default => __(':name can\'t play at **:event** on :when.', $replacements),
        };

        return (new MailMessage)
            ->subject($this->summary())
            ->greeting(__('Hi :name,', ['name' => $this->ownerName()]))
            ->line($line)
            ->action(__('View your bookings'), route('bookings.index'));
    }

    protected function summary(): string
    {
        $replacements = ['name' => $this->recipientName(), 'event' => $this->booking->event->title];

        return $this->accepted()
            ? __(':name accepted your booking request for :event.', $replacements)
            : __(':name declined your booking request for :event.', $replacements);
    }

    private function accepted(): bool
    {
        return $this->booking->status === BookingStatus::Accepted;
    }
}
