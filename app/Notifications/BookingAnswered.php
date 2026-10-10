<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
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

        $mail = (new MailMessage)
            ->subject($this->summary())
            ->greeting(__('Hi :name,', ['name' => $this->ownerName()]));

        // A decline frees the slot, so point the owner straight at the event to ask someone else.
        $event = $this->booking->event;

        if ($this->accepted() && $event->status === EventStatus::Draft && $event->isReadyToPublish()) {
            return $mail->line($line)
                ->line(__('That completes your line-up and venue, so you can publish the event now.'))
                ->action(__('Publish your event'), route('events.edit', $event));
        }

        return $this->accepted()
            ? $mail->line($line)->action(__('View your bookings'), route('bookings.index'))
            : $mail->line($line)
                ->line(__('Their place is free again, so you can ask someone else who is free that day.'))
                ->action($this->forVenue() ? __('Ask another venue') : __('Book another band'), route('events.edit', $this->booking->event));
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
