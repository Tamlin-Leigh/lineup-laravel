<?php

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the seeder creates demo accounts per role with sample events and free days', function () {
    $this->seed(DatabaseSeeder::class);

    $artist = User::where('email', 'artist@example.com')->sole();

    expect(User::whereIn('email', ['venue@example.com', 'artist@example.com', 'organiser@example.com', 'admin@example.com'])->count())->toBe(4)
        ->and(User::where('role', UserRole::Venue)->count())->toBe(4)
        ->and(User::where('role', UserRole::Artist)->count())->toBe(5)
        ->and(User::where('role', UserRole::Organiser)->count())->toBe(3)
        ->and(User::where('role', UserRole::Admin)->count())->toBe(1)
        ->and($artist->events()->count())->toBe(8)
        ->and($artist->events()->inTab('active')->count())->toBe(2)
        ->and($artist->events()->inTab('draft')->count())->toBe(3)
        ->and($artist->events()->inTab('past')->count())->toBe(3)
        ->and(Event::count())->toBe(10)
        ->and($artist->receivedBookingRequests()->awaitingReply()->count())->toBe(1)
        ->and($artist->receivedBookingRequests()->where('status', BookingStatus::Accepted)->count())->toBe(1)
        ->and(User::where('email', 'venue@example.com')->sole()->receivedBookingRequests()->awaitingReply()->count())->toBe(1)
        ->and(BookingRequest::count())->toBe(5);

    foreach (['venue@example.com', 'artist@example.com', 'organiser@example.com'] as $email) {
        expect(User::where('email', $email)->sole()->availabilities()->upcoming()->exists())->toBeTrue();
    }
});
