<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Notifications\BookingCancelled;

class CancelBookings
{
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
            });
    }
}
