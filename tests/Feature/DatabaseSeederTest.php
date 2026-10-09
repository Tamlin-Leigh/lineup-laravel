<?php

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the seeder creates demo accounts per role with sample events and free days', function () {
    $this->seed(DatabaseSeeder::class);

    $band = User::where('email', 'band@example.com')->sole();

    expect(User::whereIn('email', ['venue@example.com', 'band@example.com', 'organiser@example.com', 'admin@example.com'])->count())->toBe(4)
        ->and(User::where('role', UserRole::Venue)->count())->toBe(4)
        ->and(User::where('role', UserRole::Band)->count())->toBe(5)
        ->and(User::where('role', UserRole::Organiser)->count())->toBe(3)
        ->and(User::where('role', UserRole::Admin)->count())->toBe(1)
        ->and($band->events()->count())->toBe(8)
        ->and($band->events()->inTab('active')->count())->toBe(3)
        ->and($band->events()->inTab('draft')->count())->toBe(2)
        ->and($band->events()->inTab('past')->count())->toBe(3)
        ->and(Event::count())->toBe(10);

    foreach (['venue@example.com', 'band@example.com', 'organiser@example.com'] as $email) {
        expect(User::where('email', $email)->sole()->availabilities()->upcoming()->exists())->toBeTrue();
    }
});
