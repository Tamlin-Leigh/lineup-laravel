<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Booking requests between the demo accounts, one for each way of booking:
 * - the demo venue's open mic asks the demo artist to play (waiting for the artist's reply);
 * - the demo artist's next headline show asks the demo venue to host (waiting for the venue's reply);
 * - the demo organiser's festival has the demo artist confirmed (with a chat under way) and two more
 *   artists still to reply.
 *
 * Runs as part of DatabaseSeeder, or on its own against an existing database:
 * `php artisan db:seed --class=BookingDemoSeeder`.
 */
class BookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $venue = User::where('email', 'venue@example.com')->first();
        $artist = User::where('email', 'artist@example.com')->first();
        $openMic = $this->firstUpcomingEvent('venue@example.com');
        $headline = $this->firstUpcomingEvent('artist@example.com');
        $festival = $this->firstUpcomingEvent('organiser@example.com');

        if ($venue === null || $artist === null || $openMic === null || $headline === null || $festival === null) {
            $this->command->warn('Seed the demo accounts and their events first (php artisan db:seed).');

            return;
        }

        $this->request($openMic, $artist, [
            'status' => BookingStatus::Requested,
            'message' => 'We would love a 30 minute opening set. Backline provided.',
            'expires_at' => BookingRequest::deadlineFor($openMic),
        ]);

        $this->request($headline, $venue, [
            'status' => BookingStatus::Requested,
            'message' => 'Full artist, around 200 people expected. We bring our own sound engineer.',
            'expires_at' => BookingRequest::deadlineFor($headline),
        ]);

        $this->request($festival, $artist, [
            'status' => BookingStatus::Accepted,
            'expires_at' => now()->addDays(2),
            'responded_at' => now()->subDays(2),
        ]);
        $this->seedChat($festival->bookingRequests()->where('recipient_id', $artist->id)->sole());

        User::query()
            ->where('role', UserRole::Artist)
            ->whereKeyNot($artist->id)
            ->whereNotNull('email_verified_at')
            ->where('email', 'like', '%@example.%') // demo accounts only, never real sign-ups
            ->orderBy('id')
            ->limit(2)
            ->get()
            ->each(fn (User $other) => $this->request($festival, $other, [
                'status' => BookingStatus::Requested,
                'expires_at' => BookingRequest::deadlineFor($festival),
            ]));
    }

    /**
     * A short chat about the confirmed festival slot, with the artist's last message still unread
     * by the organiser. Written directly (not through SendMessage) so seeding sends no emails.
     */
    private function seedChat(BookingRequest $booking): void
    {
        $conversation = Conversation::forBooking($booking);

        if ($conversation->messages()->exists()) {
            return;
        }

        $owner = $conversation->owner;
        $artist = $conversation->recipient;
        $at = now()->subDays(2);

        $lines = [
            [null, $artist->displayName().' accepted the booking request for '.$booking->event->title.'. Use this chat to sort out set times, soundcheck, fees and anything else.'],
            [$owner, 'Thanks for jumping on! You are on the main stage at 15:30, 45 minute set. Soundcheck at 11:00.'],
            [$artist, 'Perfect. Can we use the house drum kit, or should we bring ours?'],
            [$owner, 'House kit is a DW, cymbals not included. Bring your own snare if you are fussy about it.'],
            [$artist, 'Great, we will bring cymbals and a snare. Is parking available for the van?'],
        ];

        foreach ($lines as $i => [$sender, $body]) {
            $message = $conversation->messages()->create(['user_id' => $sender?->id, 'body' => $body]);
            $message->forceFill(['created_at' => $at->addHours($i * 3), 'updated_at' => $at->addHours($i * 3)])->save();
        }

        $conversation->forceFill([
            'last_message_at' => $at->addHours(12),
            'owner_read_at' => $at->addHours(10),
            'recipient_read_at' => $at->addHours(12),
        ])->save();
    }

    private function firstUpcomingEvent(string $ownerEmail): ?Event
    {
        return Event::query()
            ->whereRelation('owner', 'email', $ownerEmail)
            ->upcoming()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function request(Event $event, User $recipient, array $attributes): void
    {
        $event->bookingRequests()->updateOrCreate(['recipient_id' => $recipient->id], $attributes);
    }
}
