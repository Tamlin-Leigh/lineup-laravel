<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to an artist asked to play at an event, or a venue asked to host one.
 */
class BookingRequested extends BookingNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->booking->event;
        $replacements = [
            'owner' => $this->ownerName(),
            'role' => strtolower($event->owner->role->label()),
            'event' => $event->title,
        ];

        $mail = (new MailMessage)
            ->subject(__('Booking request: :event on :date', ['event' => $event->title, 'date' => $event->starts_at->format('j M')]))
            ->greeting(__('Hi :name,', ['name' => $this->recipientName()]))
            ->line($this->forVenue()
                ? __(':owner (:role) would like to hold **:event** at your venue.', $replacements)
                : __(':owner (:role) would like to book you for **:event**.', $replacements))
            ->line(__('**When:** :when', ['when' => $this->when()]))
            ->line(__('**Where:** :location', ['location' => $event->location]))
            ->line(__('**Tickets:** :price', ['price' => $event->priceLabel()]));

        if (filled($this->booking->message)) {
            $mail->line(__('**Their message:** “:message”', ['message' => $this->booking->message]));
        }

        return $mail
            ->action(__('Reply to the request'), route('bookings.index'))
            ->line(__('Please accept or decline by :deadline. After that the request expires.', [
                'deadline' => $this->booking->expires_at->format('D j M \a\t H:i'),
            ]));
    }

    protected function summary(): string
    {
        $replacements = ['owner' => $this->ownerName(), 'event' => $this->booking->event->title];

        return $this->forVenue()
            ? __(':owner would like to hold :event at your venue.', $replacements)
            : __(':owner would like to book you for :event.', $replacements);
    }
}
