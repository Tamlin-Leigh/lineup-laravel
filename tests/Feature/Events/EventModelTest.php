<?php

use App\Models\Event;
use App\Models\User;

test('upcoming and past split events at the current moment', function () {
    $user = User::factory()->artist()->create();
    $soon = Event::factory()->for($user, 'owner')->create(['starts_at' => now()->addHour()]);
    $later = Event::factory()->for($user, 'owner')->create(['starts_at' => now()->addWeek()]);
    $recent = Event::factory()->for($user, 'owner')->create(['starts_at' => now()->subHour()]);
    $older = Event::factory()->for($user, 'owner')->create(['starts_at' => now()->subWeek()]);

    expect(Event::upcoming()->pluck('id')->all())->toBe([$soon->id, $later->id])
        ->and(Event::past()->pluck('id')->all())->toBe([$recent->id, $older->id]);
});

test('each tab holds the right events', function () {
    $user = User::factory()->artist()->create();
    $active = Event::factory()->for($user, 'owner')->create();
    $draft = Event::factory()->for($user, 'owner')->draft()->create();
    $past = Event::factory()->for($user, 'owner')->past()->create();
    $cancelled = Event::factory()->for($user, 'owner')->cancelled()->create();
    Event::factory()->for($user, 'owner')->draft()->past()->create();

    expect(Event::inTab('active')->pluck('id')->all())->toBe([$active->id])
        ->and(Event::inTab('past')->pluck('id')->all())->toBe([$past->id])
        ->and(Event::inTab('cancelled')->pluck('id')->all())->toBe([$cancelled->id])
        ->and(Event::inTab('draft')->pluck('id'))->toContain($draft->id)->toHaveCount(2);
});

test('free events are labelled as free', function () {
    expect(Event::factory()->make(['price' => 0])->priceLabel())->toBe('Free')
        ->and(Event::factory()->make(['price' => 1500])->priceLabel())->toBe('R1,500');
});

test('deleting a venue keeps events that were held there', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->create(['venue_profile_id' => $venue->venueProfile->id]);

    $venue->delete();

    expect($event->fresh())->not->toBeNull()
        ->venue_profile_id->toBeNull();
});
