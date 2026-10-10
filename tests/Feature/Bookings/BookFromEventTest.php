<?php

use App\Enums\BookingStatus;
use App\Enums\EventCategory;
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
        'form.category' => EventCategory::HeadlineShow->value,
        'form.location' => 'Somewhere in Cape Town',
        'form.date' => $date,
        'form.time' => '20:00',
        'form.price' => '100',
        'form.status' => EventStatus::Published->value,
    ];
}

test('each role is offered who it can book, free on the event date', function (string $state, bool $offersVenues) {
    $band = freeAccount('band', '2026-10-17');
    $venue = freeAccount('venue', '2026-10-17');
    freeAccount('organiser', '2026-10-17');
    freeAccount('band', '2026-10-18');
    $me = freeAccount($state, '2026-10-17');

    $component = Livewire::actingAs($me)->test(ManageEvent::class)->set('form.date', '2026-10-17');

    expect($component->get('freeBands')->pluck('id')->all())->toBe([$band->id])
        ->and($component->get('freeVenues')->pluck('id')->all())->toBe($offersVenues ? [$venue->id] : []);

})->with([
    'venue books bands' => ['venue', false],
    'band books a venue and bands' => ['band', true],
    'organiser books a venue and bands' => ['organiser', true],
]);

test('saving the event sends each pick a request with a five day deadline', function () {
    $first = freeAccount('band', '2026-10-24');
    $second = freeAccount('band', '2026-10-24');
    $venue = freeAccount('venue', '2026-10-24');
    $organiser = User::factory()->organiser()->create();

    Livewire::actingAs($organiser)
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('bandIds', [(string) $first->id, (string) $second->id])
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

test('a band can book other bands for its own event, but not itself', function () {
    $other = freeAccount('band', '2026-10-24');
    $me = freeAccount('band', '2026-10-24');

    Livewire::actingAs($me)
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('bandIds', [(string) $other->id, (string) $me->id])
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
    $band = User::factory()->band()->create();

    Livewire::actingAs($band)
        ->test(ManageEvent::class)
        ->set([...eventOn('2026-10-24'), 'form.location' => ''])
        ->set('venueId', (string) $venue->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($band->events()->sole()->location)->toBe($venue->name.', '.$venue->venueProfile->location);
});

test('only one venue is asked at a time', function () {
    $band = User::factory()->band()->create();
    $event = Event::factory()->for($band, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $asked = freeAccount('venue', '2026-10-24');
    freeAccount('venue', '2026-10-24');
    BookingRequest::factory()->for($event)->for($asked, 'recipient')->create();

    $component = Livewire::actingAs($band)->test(ManageEvent::class, ['event' => $event]);

    expect($component->get('freeVenues'))->toBeEmpty();
    $component->assertDontSee('Book a Venue')->assertSee('Already Asked');
});

test('another venue can be asked once the first declines', function () {
    $band = User::factory()->band()->create();
    $event = Event::factory()->for($band, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->declined()->create();
    $next = freeAccount('venue', '2026-10-24');

    Livewire::actingAs($band)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('venueId', (string) $next->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->bookingRequests()->where('recipient_id', $next->id)->exists())->toBeTrue();
});

test('the deadline is never after the event starts', function () {
    $band = freeAccount('band', '2026-10-09');

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-09'))
        ->set('bandIds', [(string) $band->id])
        ->call('save');

    expect(BookingRequest::sole()->expires_at->format('Y-m-d H:i'))->toBe('2026-10-09 20:00');
});

test('nobody who is not free that day is sent a request, even if their id is posted', function () {
    $busyBand = freeAccount('band', '2026-10-25');
    $busyVenue = freeAccount('venue', '2026-10-25');

    Livewire::actingAs(User::factory()->organiser()->create())
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('bandIds', [(string) $busyBand->id])
        ->set('venueId', (string) $busyVenue->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(BookingRequest::count())->toBe(0);
    Notification::assertNothingSent();
});

test('changing the date drops picks that are not free on the new one', function () {
    $band = freeAccount('band', '2026-10-24');
    $venue = freeAccount('venue', '2026-10-24');

    Livewire::actingAs(User::factory()->organiser()->create())
        ->test(ManageEvent::class)
        ->set('form.date', '2026-10-24')
        ->set(['bandIds' => [(string) $band->id], 'venueId' => (string) $venue->id])
        ->set('form.date', '2026-10-25')
        ->assertSet('bandIds', [])
        ->assertSet('venueId', '');
});

test('editing an event lists who was already asked and only offers the others', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $asked = freeAccount('band', '2026-10-24');
    $other = freeAccount('band', '2026-10-24');
    BookingRequest::factory()->for($event)->for($asked, 'recipient')->create();

    $component = Livewire::actingAs($venue)->test(ManageEvent::class, ['event' => $event]);

    expect($component->get('freeBands')->pluck('id')->all())->toBe([$other->id])
        ->and($component->get('bookingRequests')->pluck('recipient_id')->all())->toBe([$asked->id]);
    $component->assertSee('Already Asked')->assertSee('Awaiting reply');
});

test('requests cannot be sent for a cancelled or past event', function (array $changes, string $message) {
    $band = freeAccount('band', '2026-10-24');
    Availability::factory()->for($band)->create(['date' => '2026-10-01']);
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00']);

    Livewire::actingAs($venue)
        ->test(ManageEvent::class, ['event' => $event])
        ->set($changes)
        ->set('bandIds', [(string) $band->id])
        ->call('save')
        ->assertHasErrors(['booking'])
        ->assertSee($message);

    expect(BookingRequest::count())->toBe(0);
})->with([
    'cancelled' => [['form.status' => EventStatus::Cancelled->value], 'cancelled event'],
    'past' => [['form.date' => '2026-10-01'], 'still to come'],
]);

test('booking requests are rate limited', function () {
    $band = freeAccount('band', '2026-10-24');
    $venue = User::factory()->venue()->create();
    RateLimiter::increment('booking-requests:'.$venue->id, 3600, ManageEvent::BOOKING_REQUESTS_PER_HOUR);

    Livewire::actingAs($venue)
        ->test(ManageEvent::class)
        ->set(eventOn('2026-10-24'))
        ->set('bandIds', [(string) $band->id])
        ->call('save')
        ->assertHasErrors(['booking']);

    expect(BookingRequest::count())->toBe(0);
});

test('cancelling an event cancels its bookings and tells the bands and venue', function () {
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

test('a band hosting its own event is on the line-up from the start', function () {
    $band = User::factory()->band()->create();

    $component = Livewire::actingAs($band)->test(ManageEvent::class);

    expect($component->get('lineUp')->pluck('id')->all())->toBe([$band->id]);
});

test('the line-up is the host band plus the bands that accepted', function () {
    $host = User::factory()->band()->create();
    $event = Event::factory()->for($host, 'owner')->create(['starts_at' => '2026-10-24 20:00']);
    $accepted = BookingRequest::factory()->for($event)->accepted()->create();
    BookingRequest::factory()->for($event)->create();
    BookingRequest::factory()->for($event)->declined()->create();
    BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->accepted()->create();

    expect($event->lineUp()->pluck('id')->all())->toBe([$host->id, $accepted->recipient_id]);
});

test('venue and organiser events only list the bands that accepted', function (string $state) {
    $event = Event::factory()->for(User::factory()->{$state}(), 'owner')->create();
    $accepted = BookingRequest::factory()->for($event)->accepted()->create();

    expect($event->lineUp()->pluck('id')->all())->toBe([$accepted->recipient_id]);
})->with(['venue', 'organiser']);
