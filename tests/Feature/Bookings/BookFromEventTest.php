<?php

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Livewire\Events\ManageEvent;
use App\Models\Availability;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 09:00');
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

function freeAccount(string $state, string $date): User
{
    $user = User::factory()->{$state}()->create();
    Availability::factory()->for($user)->create(['date' => $date]);

    return $user;
}

/**
 * @return array<string, string>
 */
function eventOn(string $date): array
{
    return [
        'form.title' => 'Saturday Showcase',
        'form.location' => 'Somewhere in Cape Town',
        'form.date' => $date,
        'form.time' => '20:00',
        'form.price' => '100',
        'form.artist_slots' => '5',
        'form.status' => EventStatus::Draft->value,
    ];
}

test('each role is offered who it can book, free on the event date', function (string $state, bool $offersVenues) {
    $artist = freeAccount('artist', '2026-10-17');
    $venue = freeAccount('venue', '2026-10-17');
    freeAccount('organiser', '2026-10-17');
    freeAccount('artist', '2026-10-18');
    $me = freeAccount($state, '2026-10-17');

    $component = Livewire::actingAs($me)->test(ManageEvent::class)->set('form.date', '2026-10-17');

    expect($component->get('freeArtists')->pluck('id')->all())->toBe([$artist->id])
        ->and($component->get('freeVenues')->pluck('id')->all())->toBe($offersVenues ? [$venue->id] : []);

})->with([
    'venue books artists' => ['venue', false],
    'artist books a venue and artists' => ['artist', true],
    'organiser books a venue and artists' => ['organiser', true],
]);

test('saving the event sends each pick a request with a five day deadline', function () {
    $first = freeAccount('artist', '2026-10-24');
    $second = freeAccount('artist', '2026-10-24');
    $venue = freeAccount('venue', '2026-10-24');
    $organiser = User::factory()->organiser()->create();

    Livewire::actingAs($organiser)
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('artistIds', [(string) $first->id, (string) $second->id])
        ->set('venueId', (string) $venue->id)
        ->set('bookingMessage', 'Looking for a 45 minute set.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $requests = $organiser->events()->sole()->bookingRequests()->get();

    expect($requests->pluck('recipient_id')->sort()->values()->all())->toBe([$first->id, $second->id, $venue->id])
        ->and($requests->pluck('status')->unique()->all())->toBe([BookingStatus::Requested])
        ->and($requests->first()->message)->toBe('Looking for a 45 minute set.')
        ->and($requests->first()->expires_at->format('Y-m-d H:i'))->toBe('2026-10-13 09:00');

    Notification::assertSentTo([$first, $second, $venue], BookingRequested::class);
});

test('an artist can book other artists for its own event, but not itself', function () {
    $other = freeAccount('artist', '2026-10-24');
    $me = freeAccount('artist', '2026-10-24');

    Livewire::actingAs($me)
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('artistIds', [(string) $other->id, (string) $me->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(BookingRequest::pluck('recipient_id')->all())->toBe([$other->id]);
});

test('a venue cannot ask another venue to host its event', function () {
    $otherVenue = freeAccount('venue', '2026-10-24');

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('venueId', (string) $otherVenue->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(BookingRequest::count())->toBe(0);
});

test('an event without a location can take the picked venue as its location', function () {
    $venue = freeAccount('venue', '2026-10-24');
    $artist = User::factory()->artist()->create();

    Livewire::actingAs($artist)
        ->test(ManageEvent::class)
        ->set([...eventOn('2026-10-24'), 'form.location' => ''])
        ->set('venueId', (string) $venue->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($artist->events()->sole()->location)->toBe($venue->name.', '.$venue->venueProfile->location);
});

test('only one venue is asked at a time', function () {
    $artist = User::factory()->artist()->create();
    $event = Event::factory()->for($artist, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $asked = freeAccount('venue', '2026-10-24');
    freeAccount('venue', '2026-10-24');
    BookingRequest::factory()->for($event)->for($asked, 'recipient')->create();

    $component = Livewire::actingAs($artist)->test(ManageEvent::class, ['event' => $event]);

    expect($component->get('freeVenues'))->toBeEmpty();
    $component->assertDontSee('Book a Venue')->assertSee('Already Asked');
});

test('another venue can be asked once the first declines', function () {
    $artist = User::factory()->artist()->create();
    $event = Event::factory()->for($artist, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->declined()->create();
    $next = freeAccount('venue', '2026-10-24');

    Livewire::actingAs($artist)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('venueId', (string) $next->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->bookingRequests()->where('recipient_id', $next->id)->exists())->toBeTrue();
});

test('the deadline is never after the event starts', function () {
    $artist = freeAccount('artist', '2026-10-09');

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-09'))
        ->set('artistIds', [(string) $artist->id])
        ->call('save');

    expect(BookingRequest::sole()->expires_at->format('Y-m-d H:i'))->toBe('2026-10-09 20:00');
});

test('nobody who is not free that day is sent a request, even if their id is posted', function () {
    $busyArtist = freeAccount('artist', '2026-10-25');
    $busyVenue = freeAccount('venue', '2026-10-25');

    Livewire::actingAs(User::factory()->organiser()->create())
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('artistIds', [(string) $busyArtist->id])
        ->set('venueId', (string) $busyVenue->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(BookingRequest::count())->toBe(0);
    Notification::assertNothingSent();
});

test('changing the date drops picks that are not free on the new one', function () {
    $artist = freeAccount('artist', '2026-10-24');
    $venue = freeAccount('venue', '2026-10-24');

    Livewire::actingAs(User::factory()->organiser()->create())
        ->test(ManageEvent::class)
        ->set('form.date', '2026-10-24')
        ->set(['artistIds' => [(string) $artist->id], 'venueId' => (string) $venue->id])
        ->set('form.date', '2026-10-25')
        ->assertSet('artistIds', [])
        ->assertSet('venueId', '');
});

test('editing an event lists who was already asked and only offers the others', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $asked = freeAccount('artist', '2026-10-24');
    $other = freeAccount('artist', '2026-10-24');
    BookingRequest::factory()->for($event)->for($asked, 'recipient')->create();

    $component = Livewire::actingAs($venue)->test(ManageEvent::class, ['event' => $event]);

    expect($component->get('freeArtists')->pluck('id')->all())->toBe([$other->id])
        ->and($component->get('bookingRequests')->pluck('recipient_id')->all())->toBe([$asked->id]);
    $component->assertSee('Already Asked')->assertSee('Awaiting reply');
});

test('requests cannot be sent for a cancelled or past event', function (array $changes, string $message) {
    $artist = freeAccount('artist', '2026-10-24');
    Availability::factory()->for($artist)->create(['date' => '2026-10-01']);
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00']);

    Livewire::actingAs($venue)
        ->test(ManageEvent::class, ['event' => $event])
        ->set($changes)
        ->set('artistIds', [(string) $artist->id])
        ->call('save')
        ->assertHasErrors(['booking'])
        ->assertSee($message);

    expect(BookingRequest::count())->toBe(0);
})->with([
    'cancelled' => [['form.status' => EventStatus::Cancelled->value], 'cancelled event'],
    'past' => [['form.date' => '2026-10-01'], 'still to come'],
]);

test('booking requests are rate limited', function () {
    $artist = freeAccount('artist', '2026-10-24');
    $venue = User::factory()->venue()->create();
    RateLimiter::increment('booking-requests:'.$venue->id, 3600, ManageEvent::BOOKING_REQUESTS_PER_HOUR);

    Livewire::actingAs($venue)
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('artistIds', [(string) $artist->id])
        ->call('save')
        ->assertHasErrors(['booking']);

    expect(BookingRequest::count())->toBe(0);
});

test('cancelling an event cancels its bookings and tells the artists and venue', function () {
    $organiser = User::factory()->organiser()->create();
    $event = Event::factory()->for($organiser, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $waiting = BookingRequest::factory()->for($event)->create();
    $venue = BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->accepted()->create();
    $declined = BookingRequest::factory()->for($event)->declined()->create();

    Livewire::actingAs($organiser)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('form.status', EventStatus::Cancelled->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($waiting->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($venue->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($declined->fresh()->status)->toBe(BookingStatus::Declined);

    Notification::assertSentTo([$waiting->recipient, $venue->recipient], BookingCancelled::class);
    Notification::assertNotSentTo($declined->recipient, BookingCancelled::class);
});

test('an artist hosting its own event is on the line-up from the start', function () {
    $artist = User::factory()->artist()->create();

    $component = Livewire::actingAs($artist)->test(ManageEvent::class);

    expect($component->get('lineUp')->pluck('id')->all())->toBe([$artist->id]);
});

test('the line-up is the host artist plus the artists that accepted', function () {
    $host = User::factory()->artist()->create();
    $event = Event::factory()->for($host, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $accepted = BookingRequest::factory()->for($event)->accepted()->create();
    BookingRequest::factory()->for($event)->create();
    BookingRequest::factory()->for($event)->declined()->create();
    BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->accepted()->create();

    expect($event->lineUp()->pluck('id')->all())->toBe([$host->id, $accepted->recipient_id]);
});

test('venue and organiser events only list the artists that accepted', function (string $state) {
    $event = Event::factory()->for(User::factory()->{$state}(), 'owner')->create();
    $accepted = BookingRequest::factory()->for($event)->accepted()->create();

    expect($event->lineUp()->pluck('id')->all())->toBe([$accepted->recipient_id]);
})->with(['venue', 'organiser']);
