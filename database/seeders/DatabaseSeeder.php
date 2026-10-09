<?php

namespace Database\Seeders;

use App\Actions\UpdateAvailability;
use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one log-in-able account per role.
     * Every account's password is "password".
     */
    public function run(): void
    {
        $venue = User::factory()->venue()->create(['name' => 'Demo Venue', 'email' => 'venue@example.com']);
        $band = User::factory()->band()->create(['name' => 'Demo Band', 'email' => 'band@example.com']);
        $organiser = User::factory()->organiser()->create(['name' => 'Demo Organiser', 'email' => 'organiser@example.com']);
        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);

        $this->seedBandEvents($band);

        $venue->events()->create($this->event(
            'Open Mic Night', EventCategory::Other, 'Monthly open mic, all genres welcome.', $venue->venueProfile->location, 10, '19:00', 50,
        ) + ['venue_profile_id' => $venue->venueProfile->id]);

        $organiser->events()->create($this->event(
            'Summer Sounds Festival', EventCategory::Festival, 'Two stages, twelve acts, one long afternoon.', 'Green Point Park, Cape Town', 35, '12:00', 250,
        ));

        // Free days on upcoming weekends, so the calendar (and searching who is free) has something to show.
        $availability = app(UpdateAvailability::class);
        $availability->markFree($venue, $this->upcomingWeekendDays(4, [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY]));
        $availability->markFree($band, $this->upcomingWeekendDays(3, [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY, CarbonInterface::SUNDAY]));
        $availability->markFree($organiser, $this->upcomingWeekendDays(2, [CarbonInterface::SATURDAY]));

        $this->call(DiscoverDemoSeeder::class);
    }

    /**
     * The given weekdays over the next few weeks, starting tomorrow.
     *
     * @param  list<int>  $weekdays  Carbon day-of-week numbers.
     * @return list<CarbonImmutable>
     */
    private function upcomingWeekendDays(int $weeks, array $weekdays): array
    {
        $days = [];

        for ($day = CarbonImmutable::tomorrow(); $day->lte(CarbonImmutable::tomorrow()->addWeeks($weeks)); $day = $day->addDay()) {
            if (in_array($day->dayOfWeek, $weekdays, true)) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * The prototype's sample events, scheduled relative to today so there is always something upcoming and past.
     */
    private function seedBandEvents(User $band): void
    {
        $events = [
            ['Headline Show - The Loft', EventCategory::HeadlineShow, 'Our biggest headline show of the year, with support from two local acts.', 'The Loft, Cape Town', 14, '20:00', 150],
            ['Echo Beats Festival', EventCategory::Festival, 'Direct support slot on the main stage, 45 minute set.', 'Sunset Park, Johannesburg', 25, '18:00', 120],
            ['Acoustic Sessions', EventCategory::AcousticSet, 'Stripped-back acoustic set in an intimate venue, limited capacity.', 'The Brass Room, Durban', 40, '19:30', 90],
            ['Private Corporate Function', EventCategory::PrivateEvent, 'Private set for a corporate end-of-year function, details tbc.', 'Rocky Ridge Hall, Pretoria', 55, '21:00', 0, EventStatus::Draft],
            ['Runway Revolution Afterparty', EventCategory::SupportSlot, 'Late-night set closing out the Runway Revolution fashion event.', 'Vogue Hall, Johannesburg', 70, '23:00', 0, EventStatus::Draft],
            ['Winter Warehouse Show', EventCategory::HeadlineShow, 'A one-off warehouse show with full production and visuals.', 'The Warehouse, Pretoria', -90, '20:00', 180],
            ['Tech Future Expo Showcase', EventCategory::SupportSlot, "Short live showcase set at the Tech Future Expo's evening session.", 'CTICC, Cape Town', -60, '19:00', 60],
            ['Artistry Unveiled Afterparty', EventCategory::Festival, 'DJ and live set closing out the Artistry Unveiled Expo opening night.', 'Modern Art Gallery, Durban', -100, '22:00', 100],
        ];

        foreach ($events as $event) {
            $band->events()->create($this->event(...$event));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function event(
        string $title,
        EventCategory $category,
        string $description,
        string $location,
        int $daysFromToday,
        string $time,
        int $price,
        EventStatus $status = EventStatus::Published,
    ): array {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return [
            'title' => $title,
            'category' => $category,
            'description' => $description,
            'location' => $location,
            'starts_at' => now()->addDays($daysFromToday)->setTime($hour, $minute),
            'price' => $price,
            'status' => $status,
        ];
    }
}
