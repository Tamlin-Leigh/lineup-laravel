<?php

use App\Enums\BookingStatus;
use App\Livewire\Bookings\Index;
use App\Livewire\Events\Show;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 09:00');
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('an artist asked to play can see a draft event before answering', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create([
        'description' => 'Album launch with a late bar.',
        'price' => 120,
        'artist_slots' => 3,
    ]);
    $booking = BookingRequest::factory()->for($event)->create(['message' => 'Looking for a 45 minute set']);

    $this->actingAs($booking->recipient)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee('Album launch with a late bar.')
        ->assertSee('R120')
        ->assertSee('3 artists playing')
        ->assertSee($venue->displayName())
        ->assertSee('Looking for a 45 minute set')
        ->assertSee('data-test="accept-booking"', false);
});

test('a draft event stays hidden from accounts that were not asked', function () {
    $event = Event::factory()->draft()->create();
    BookingRequest::factory()->for($event)->create();

    $this->actingAs(User::factory()->artist()->create())
        ->get(route('events.show', $event))
        ->assertForbidden();
});

test('published events can be viewed by anyone signed in', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->organiser()->create())
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertDontSee('data-test="your-booking"', false);
});

test('the line-up shows the host artist and artists that accepted', function () {
    $host = User::factory()->artist()->create();
    $event = Event::factory()->for($host, 'owner')->create();
    $booked = BookingRequest::factory()->for($event)->accepted()->create();
    $waiting = BookingRequest::factory()->for($event)->create();

    Livewire::actingAs($waiting->recipient)->test(Show::class, ['event' => $event])
        ->assertSeeInOrder([$host->displayName(), $booked->recipient->displayName()])
        ->assertSee('Host')
        ->assertSee('Booked');
});

test('invited artists can see who else was invited and is still deciding', function () {
    $event = Event::factory()->create();
    $mine = BookingRequest::factory()->for($event)->create();
    $other = BookingRequest::factory()->for($event)->create();
    $declined = BookingRequest::factory()->for($event)->declined()->create();
    $expired = BookingRequest::factory()->for($event)->overdue()->create();
    $venue = BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->create();

    Livewire::actingAs($mine->recipient)->test(Show::class, ['event' => $event])
        ->assertSeeHtml('data-test="invited-artists"')
        ->assertSee($other->recipient->displayName())
        ->assertDontSee($declined->recipient->displayName())
        ->assertDontSee($expired->recipient->displayName())
        ->assertDontSee($venue->recipient->displayName());
});

test('the host can see the invited artists', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs($booking->event->owner)->test(Show::class, ['event' => $booking->event])
        ->assertSeeHtml('data-test="invited-artists"')
        ->assertSee($booking->recipient->displayName());
});

test('accounts not involved in a published event cannot see who was invited', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs(User::factory()->artist()->create())->test(Show::class, ['event' => $booking->event])
        ->assertDontSeeHtml('data-test="invited-artists"')
        ->assertDontSee($booking->recipient->displayName());
});

test('an artist moves from invited to booked when they accept', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs($booking->recipient)->test(Show::class, ['event' => $booking->event])
        ->assertSeeHtml('data-test="invited-artists"')
        ->call('accept')
        ->assertDontSeeHtml('data-test="invited-artists"')
        ->assertSee('Booked');
});

test('the request can be accepted from the event page', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs($booking->recipient)->test(Show::class, ['event' => $booking->event])
        ->call('accept')
        ->assertHasNoErrors()
        ->assertDontSee('data-test="accept-booking"', false);

    expect($booking->fresh()->status)->toBe(BookingStatus::Accepted);
});

test('the request can be declined from the event page', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs($booking->recipient)->test(Show::class, ['event' => $booking->event])
        ->call('decline');

    expect($booking->fresh()->status)->toBe(BookingStatus::Declined);
});

test('an expired request shows a message instead of being accepted', function () {
    $booking = BookingRequest::factory()->overdue()->create();

    Livewire::actingAs($booking->recipient)->test(Show::class, ['event' => $booking->event])
        ->call('accept')
        ->assertHasErrors('booking');

    expect($booking->fresh()->status)->not->toBe(BookingStatus::Accepted);
});

test('someone with no request for the event cannot answer for it', function () {
    $event = Event::factory()->create();

    Livewire::actingAs(User::factory()->artist()->create())->test(Show::class, ['event' => $event])
        ->call('accept')
        ->assertForbidden();
});

test('booking rows link to the event', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs($booking->recipient)->test(Index::class)
        ->assertSeeHtml('href="'.route('events.show', $booking->event).'"');
});
