<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Conversation;
use App\Models\Event;
use App\Notifications\BookingCancelled;

class CancelBookings
{
    public function __construct(private SendMessage $messages) {}

    /**
     * The event is off: cancel the open and accepted requests and let the bands and venues know.
     */
    public function handle(Event $event): void
    {
        $event->bookingRequests()
            ->with('recipient')
            ->whereIn('status', [BookingStatus::Requested, BookingStatus::Accepted])
            ->get()
            ->each(function (BookingRequest $booking) use ($event) {
                // A request that ran out unanswered just quietly expires.
                if ($booking->currentStatus() === BookingStatus::Expired) {
                    $booking->update(['status' => BookingStatus::Expired]);

                    return;
                }

                $booking->update(['status' => BookingStatus::Cancelled]);
                $booking->recipient->notify(new BookingCancelled($booking->setRelation('event', $event)));

                // Leave a note in the booking's chat (now read-only) so the history explains itself.
                if ($conversation = Conversation::where('booking_request_id', $booking->id)->first()) {
                    $this->messages->handle($conversation, null, __(':owner cancelled :event.', [
                        'owner' => $event->owner->displayName(),
                        'event' => $event->title,
                    ]));
                }
            });
    }
}
