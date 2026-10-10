<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to a band or venue that was asked (or booked) for an event its owner has cancelled.
 */
class BookingCancelled extends BookingNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->summary())
            ->greeting(__('Hi :name,', ['name' => $this->recipientName()]))
            ->line(__(':owner has cancelled **:event**, which was on :when, so you are no longer booked for it.', [
                'owner' => $this->ownerName(),
                'event' => $this->booking->event->title,
                'when' => $this->when(),
            ]))
            ->action(__('View your bookings'), route('bookings.index'));
    }

    protected function summary(): string
    {
        return __(':event has been cancelled.', ['event' => $this->booking->event->title]);
    }
}
