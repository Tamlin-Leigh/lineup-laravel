<?php

namespace App\Policies;

use App\Models\BookingRequest;
use App\Models\User;

class BookingRequestPolicy
{
    /**
     * Only the band or venue that was asked can accept or decline, and only before the deadline.
     */
    public function respond(User $user, BookingRequest $booking): bool
    {
        return $user->id === $booking->recipient_id && $booking->isAwaitingReply();
    }
}
