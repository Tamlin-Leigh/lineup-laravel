<?php

use App\Enums\EventCategory;
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
        'form.category' => EventCategory::HeadlineShow->value,
        'form.location' => 'The Loft, Cape Town',
        'form.date' => now()->addWeek()->format('Y-m-d'),
        'form.time' => '21:30',
        'form.price' => '120',
        'form.status' => EventStatus::Published->value,
    ];
}

test('a user can create an event', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)
        ->test(ManageEvent::class)
        ->set(validEvent())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    expect($user->events()->sole())
        ->title->toBe('Friday Late Show')
        ->category->toBe(EventCategory::HeadlineShow)
        ->price->toBe(120)
        ->status->toBe(EventStatus::Published)
        ->and($user->events()->sole()->starts_at->format('Y-m-d H:i'))->toBe(now()->addWeek()->format('Y-m-d').' 21:30');
});

test('required fields are validated', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->test(ManageEvent::class)
        ->set(['form.title' => '', 'form.category' => '', 'form.location' => '', 'form.time' => '25:00', 'form.price' => '-5'])
        ->call('save')
        ->assertHasErrors(['form.title', 'form.category', 'form.location', 'form.time', 'form.price']);
});

test('a new event cannot start out cancelled', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->test(ManageEvent::class)
        ->set([...validEvent(), 'form.status' => EventStatus::Cancelled->value])
        ->call('save')
        ->assertHasErrors(['form.status']);
});

test('the date picked in the calendar is pre-filled', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->withQueryParams(['date' => '2027-03-14'])
        ->test(ManageEvent::class)
        ->assertSet('form.date', '2027-03-14');
});

test('an invalid date in the link falls back to tomorrow', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->withQueryParams(['date' => 'not-a-date'])
        ->test(ManageEvent::class)
        ->assertSet('form.date', now()->addDay()->format('Y-m-d'));
});

test('venues start from their own venue', function () {
    $user = User::factory()->venue()->create();

    Livewire::actingAs($user)
        ->test(ManageEvent::class)
        ->assertSet('form.venue_profile_id', (string) $user->venueProfile->id)
        ->assertSet('form.location', $user->venueProfile->location);
});

test('picking a BandLink venue fills in an empty location', function () {
    $venue = User::factory()->venue()->create()->venueProfile;

    Livewire::actingAs(User::factory()->band()->create())
        ->test(ManageEvent::class)
        ->set('form.venue_profile_id', (string) $venue->id)
        ->assertSet('form.location', $venue->location)
        ->set(['form.location' => 'Typed by hand', 'form.venue_profile_id' => ''])
        ->assertSet('form.location', 'Typed by hand');
});

test('an event can be linked to a BandLink venue', function () {
    $user = User::factory()->band()->create();
    $venue = User::factory()->venue()->create()->venueProfile;

    Livewire::actingAs($user)
        ->test(ManageEvent::class)
        ->set([...validEvent(), 'form.venue_profile_id' => (string) $venue->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->events()->sole()->venue->is($venue))->toBeTrue();
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

    $this->actingAs(User::factory()->band()->create())
        ->get(route('events.edit', $event))
        ->assertForbidden();
});
