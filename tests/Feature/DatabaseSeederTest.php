<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the seeder creates a demo account per role with sample events', function () {
    $this->seed(DatabaseSeeder::class);

    $band = User::where('email', 'band@example.com')->sole();

    expect(User::pluck('role')->all())->toEqualCanonicalizing([UserRole::Venue, UserRole::Band, UserRole::Organiser, UserRole::Admin])
        ->and($band->events()->count())->toBe(8)
        ->and($band->events()->inTab('active')->count())->toBe(3)
        ->and($band->events()->inTab('draft')->count())->toBe(2)
        ->and($band->events()->inTab('past')->count())->toBe(3)
        ->and(Event::count())->toBe(10)
        ->and(User::all()->filter(fn (User $user) => $user->role !== UserRole::Admin)->every(
            fn (User $user) => $user->availabilities()->upcoming()->exists(),
        ))->toBeTrue();
});
