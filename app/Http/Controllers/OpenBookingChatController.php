<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OpenBookingChatController extends Controller
{
    /**
     * The "Message" button on a booking: open its chat (starting it if needed). Only the event's
     * owner and the band or venue on the booking can, once it has been accepted.
     */
    public function __invoke(Request $request, BookingRequest $booking): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $existing = Conversation::where('booking_request_id', $booking->id)->first();

        $isOnBooking = $user->id === $booking->event->user_id || $user->id === $booking->recipient_id;

        abort_unless($isOnBooking, 403);
        abort_unless($existing !== null || $booking->status === BookingStatus::Accepted, 403);

        return to_route('messages.show', $existing ?? Conversation::forBooking($booking));
    }
}
