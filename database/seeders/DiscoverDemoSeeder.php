<?php

namespace Database\Seeders;

use App\Actions\UpdateAvailability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Extra venues, bands and organisers with a mix of free days, so Discover has people to find.
 * Safe to run on its own against an existing database (it only adds accounts):
 *
 *     php artisan db:seed --class=DiscoverDemoSeeder
 */
class DiscoverDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(UpdateAvailability $availability): void
    {
        foreach (['venue' => 3, 'band' => 4, 'organiser' => 2] as $state => $count) {
            User::factory()->{$state}()->count($count)->create()->each(
                fn (User $user) => $availability->markFree($user, $this->someUpcomingDays()),
            );
        }
    }

    /**
     * Over the next six weeks: each Friday to Sunday has an even chance, other days a small one.
     *
     * @return list<CarbonImmutable>
     */
    private function someUpcomingDays(): array
    {
        $days = [];

        for ($day = CarbonImmutable::tomorrow(); $day->lte(CarbonImmutable::tomorrow()->addWeeks(6)); $day = $day->addDay()) {
            if (fake()->boolean($day->isWeekend() || $day->isFriday() ? 55 : 10)) {
                $days[] = $day;
            }
        }

        return $days;
    }
}
