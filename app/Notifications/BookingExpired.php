<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the event's owner when a band or venue lets a request run out without replying.
 */
class BookingExpired extends BookingNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->summary())
            ->greeting(__('Hi :name,', ['name' => $this->ownerName()]))
            ->line(__(':name didn\'t reply in time, so the request has expired. You can ask someone else who is free on :when.', [
                'name' => $this->recipientName(),
                'when' => $this->when(),
            ]))
            ->action(__('Book someone else'), route('events.edit', $this->booking->event));
    }

    protected function summary(): string
    {
        return __(':name didn\'t reply to your booking request for :event.', [
            'name' => $this->recipientName(),
            'event' => $this->booking->event->title,
        ]);
    }
}
