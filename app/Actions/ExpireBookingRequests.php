<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Notifications\BookingExpired;

class ExpireBookingRequests
{
    /**
     * Store unanswered requests past their deadline as expired and tell each event's owner.
     *
     * @return int How many requests expired.
     */
    public function handle(): int
    {
        $count = 0;

        BookingRequest::overdue()->with(['event.owner', 'recipient'])->chunkById(100, function ($bookings) use (&$count) {
            foreach ($bookings as $booking) {
                $booking->update(['status' => BookingStatus::Expired]);
                $booking->event->owner->notify(new BookingExpired($booking));
                $count++;
            }
        });

        return $count;
    }
}
