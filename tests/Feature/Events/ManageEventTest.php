<?php

use App\Enums\EventStatus;
use App\Livewire\Events\ManageEvent;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array<string, string>
 */
function validEvent(): array
{
    return [
        'form.title' => 'Friday Late Show',
        'form.location' => 'The Loft, Cape Town',
        'form.date' => now()->addWeek()->format('Y-m-d'),
        'form.time' => '21:30',
        'form.price' => '120',
        'form.status' => EventStatus::Published->value,
    ];
}

test('a user can create an event', function () {
    $user = User::factory()->artist()->create();

    Livewire::actingAs($user)
        ->test(ManageEvent::class)
        ->set(validEvent())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    expect($user->events()->sole())
        ->title->toBe('Friday Late Show')
        ->price->toBe(120)
        ->status->toBe(EventStatus::Published)
        ->and($user->events()->sole()->starts_at->format('Y-m-d H:i'))->toBe(now()->addWeek()->format('Y-m-d').' 21:30');
});

test('required fields are validated', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->test(ManageEvent::class)
        ->set(['form.title' => '', 'form.location' => '', 'form.time' => '25:00', 'form.price' => '-5', 'form.artist_slots' => '0'])
        ->call('save')
        ->assertHasErrors(['form.title', 'form.location', 'form.time', 'form.price', 'form.artist_slots']);
});

test('a new event cannot start out cancelled', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->test(ManageEvent::class)
        ->set([...validEvent(), 'form.status' => EventStatus::Cancelled->value])
        ->call('save')
        ->assertHasErrors(['form.status']);
});

test('the date picked in the calendar is pre-filled', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->withQueryParams(['date' => '2027-03-14'])
        ->test(ManageEvent::class)
        ->assertSet('form.date', '2027-03-14');
});

test('an invalid date in the link falls back to tomorrow', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->withQueryParams(['date' => 'not-a-date'])
        ->test(ManageEvent::class)
        ->assertSet('form.date', now()->addDay()->format('Y-m-d'));
});

test('venues hold their events at their own venue', function () {
    $user = User::factory()->venue()->create();

    Livewire::actingAs($user)
        ->test(ManageEvent::class)
        ->assertSet('form.location', $user->venueProfile->location)
        ->set([...validEvent(), 'form.status' => EventStatus::Draft->value])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->events()->sole()->venue->is($user->venueProfile))->toBeTrue();
});

test('other events are only linked to a LineUp venue once it accepts a booking request', function () {
    $user = User::factory()->artist()->create();

    Livewire::actingAs($user)
        ->test(ManageEvent::class)
        ->set(validEvent())
        ->call('save')
        ->assertHasNoErrors();

    expect($user->events()->sole()->venue_profile_id)->toBeNull();
});

test('the owner can edit and cancel an event', function () {
    $event = Event::factory()->create(['title' => 'Old Title']);

    Livewire::actingAs($event->owner)
        ->test(ManageEvent::class, ['event' => $event])
        ->assertSet('form.title', 'Old Title')
        ->set(['form.title' => 'New Title', 'form.status' => EventStatus::Cancelled->value])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh())
        ->title->toBe('New Title')
        ->status->toBe(EventStatus::Cancelled);
});

test("users cannot edit someone else's event", function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->artist()->create())
        ->get(route('events.edit', $event))
        ->assertForbidden();
});
