<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Booking requests between the demo accounts, one for each way of booking:
 * - the demo venue's open mic asks the demo band to play (waiting for the band's reply);
 * - the demo band's next headline show asks the demo venue to host (waiting for the venue's reply);
 * - the demo organiser's festival has the demo band confirmed and two more bands still to reply.
 *
 * Runs as part of DatabaseSeeder, or on its own against an existing database:
 * `php artisan db:seed --class=BookingDemoSeeder`.
 */
class BookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $venue = User::where('email', 'venue@example.com')->first();
        $band = User::where('email', 'band@example.com')->first();
        $openMic = $this->firstUpcomingEvent('venue@example.com');
        $headline = $this->firstUpcomingEvent('band@example.com');
        $festival = $this->firstUpcomingEvent('organiser@example.com');

        if ($venue === null || $band === null || $openMic === null || $headline === null || $festival === null) {
            $this->command->warn('Seed the demo accounts and their events first (php artisan db:seed).');

            return;
        }

        $this->request($openMic, $band, [
            'status' => BookingStatus::Requested,
            'message' => 'We would love a 30 minute opening set. Backline provided.',
            'expires_at' => BookingRequest::deadlineFor($openMic),
        ]);

        $this->request($headline, $venue, [
            'status' => BookingStatus::Requested,
            'message' => 'Full band, around 200 people expected. We bring our own sound engineer.',
            'expires_at' => BookingRequest::deadlineFor($headline),
        ]);

        $this->request($festival, $band, [
            'status' => BookingStatus::Accepted,
            'expires_at' => now()->addDays(2),
            'responded_at' => now()->subDays(2),
        ]);

        User::query()
            ->where('role', UserRole::Band)
            ->whereKeyNot($band->id)
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

    private function firstUpcomingEvent(string $ownerEmail): ?Event
    {
        return Event::query()
            ->whereRelation('owner', 'email', $ownerEmail)
            ->published()
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
