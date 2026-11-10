<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BookingRequested;
use App\Queries\AvailableAccounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendBookingRequests
{
    public function __construct(private AvailableAccounts $available) {}

    /**
     * Ask each account to take part in the event, by email and on their Bookings page. Only
     * accounts the owner may book (see User::bookableRoles()) that are free on the event's date and
     * haven't been asked for this event already are sent one, no more artists than the event has open
     * artist slots, and at most one venue at a time.
     *
     * @param  array<int|string>  $recipientIds
     * @return Collection<int, BookingRequest> The requests that were sent.
     */
    public function handle(Event $event, array $recipientIds, ?string $message = null): Collection
    {
        $day = $event->starts_at->toImmutable()->startOfDay();

        $recipients = $this->available->query($event->owner, $day, $day, $event->owner->bookableRoles())
            ->whereKey(array_map('intval', $recipientIds))
            ->whereDoesntHave('receivedBookingRequests', fn ($query) => $query->where('event_id', $event->id))
            ->get();

        [$venues, $others] = $recipients->partition(fn (User $user) => $user->role === UserRole::Venue);
        $recipients = $others->take($event->openArtistSlots())->concat($event->needsVenue() ? $venues->take(1) : []);

        $requests = DB::transaction(fn () => $recipients->map(fn (User $recipient) => $event->bookingRequests()->create([
            'recipient_id' => $recipient->id,
            'status' => BookingStatus::Requested,
            'message' => filled($message) ? trim($message) : null,
            'expires_at' => BookingRequest::deadlineFor($event),
        ])->setRelation('recipient', $recipient)->setRelation('event', $event)));

        $requests->each(fn (BookingRequest $request) => $request->recipient->notify(new BookingRequested($request)));

        return $requests->values();
    }
}
