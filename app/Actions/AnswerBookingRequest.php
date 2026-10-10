<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Notifications\BookingAnswered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnswerBookingRequest
{
    /**
     * The band or venue says yes: that day is no longer free on their calendar, and a venue
     * becomes the event's venue.
     *
     * @throws ValidationException when the request can't be answered or the recipient is already booked that day.
     */
    public function accept(BookingRequest $booking): void
    {
        $this->ensureAwaitingReply($booking);

        $event = $booking->event;
        $recipient = $booking->recipient;
        $date = $event->starts_at->toDateString();

        $alreadyBooked = $recipient->receivedBookingRequests()
            ->where('status', BookingStatus::Accepted)
            ->whereHas('event', fn ($query) => $query->whereDate('starts_at', $date))
            ->exists();

        if ($alreadyBooked) {
            throw ValidationException::withMessages([
                'booking' => __('You are already booked for another event on that day.'),
            ]);
        }

        // A band hosting its own event that day is on that line-up already.
        $hostingOwnEvent = $recipient->role === UserRole::Band && $recipient->events()
            ->where('status', '!=', EventStatus::Cancelled)
            ->whereDate('starts_at', $date)
            ->exists();

        if ($hostingOwnEvent) {
            throw ValidationException::withMessages([
                'booking' => __('You are playing your own event on that day.'),
            ]);
        }

        DB::transaction(function () use ($booking, $event, $recipient, $date) {
            $this->answer($booking, BookingStatus::Accepted);
            $recipient->availabilities()->whereDate('date', $date)->delete();

            if ($recipient->role === UserRole::Venue && $recipient->venueProfile !== null) {
                $event->update([
                    'venue_profile_id' => $recipient->venueProfile->id,
                    'location' => $recipient->name.', '.$recipient->venueProfile->location,
                ]);
            }
        });

        $event->owner->notify(new BookingAnswered($booking));
    }

    /**
     * @throws ValidationException when the request can no longer be answered.
     */
    public function decline(BookingRequest $booking): void
    {
        $this->ensureAwaitingReply($booking);

        $this->answer($booking, BookingStatus::Declined);

        $booking->event->owner->notify(new BookingAnswered($booking));
    }

    private function answer(BookingRequest $booking, BookingStatus $status): void
    {
        $booking->update(['status' => $status, 'responded_at' => now()]);
    }

    private function ensureAwaitingReply(BookingRequest $booking): void
    {
        if (! $booking->isAwaitingReply()) {
            throw ValidationException::withMessages([
                'booking' => __('This request can no longer be answered.'),
            ]);
        }
    }
}
