<?php

use App\Enums\BookingStatus;
use App\Livewire\Bookings\Index;
use App\Models\Availability;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BookingAnswered;
use App\Notifications\BookingRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 09:00');
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the bookings page is for venues, artists and organisers', function (string $state, int $status) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('bookings.index'))
        ->assertStatus($status);
})->with([
    'venue' => ['venue', 200],
    'artist' => ['artist', 200],
    'organiser' => ['organiser', 200],
    'admin' => ['admin', 403],
]);

test('received requests are grouped by what needs a reply', function () {
    $artist = User::factory()->artist()->create();
    $waiting = BookingRequest::factory()->for($artist, 'recipient')->create(['message' => 'Bring your own drum kit']);
    $confirmed = BookingRequest::factory()->for($artist, 'recipient')->accepted()->create();
    $expired = BookingRequest::factory()->for($artist, 'recipient')->overdue()->create();
    BookingRequest::factory()->create();

    $component = Livewire::actingAs($artist)->test(Index::class);
    $bookings = $component->get('bookings');

    expect($component->instance()->activeTab())->toBe('received')
        ->and($bookings['awaiting']->pluck('id')->all())->toBe([$waiting->id])
        ->and($bookings['confirmed']->pluck('id')->all())->toBe([$confirmed->id])
        ->and($bookings['closed']->pluck('id')->all())->toBe([$expired->id]);

    $component->assertSee($waiting->event->owner->displayName())
        ->assertSee('Bring your own drum kit')
        ->assertSee('No reply')
        ->assertSeeHtml('data-test="accept-booking-'.$waiting->id.'"');
});

test('the sent tab lists requests for your own events', function () {
    $artist = User::factory()->artist()->create();
    $mine = BookingRequest::factory()->for(Event::factory()->for($artist, 'owner'))->for(User::factory()->venue(), 'recipient')->create();
    BookingRequest::factory()->for($artist, 'recipient')->create();

    $component = Livewire::actingAs($artist)->test(Index::class)->set('tab', 'sent');

    expect($component->get('bookings')['awaiting']->pluck('id')->all())->toBe([$mine->id]);
    $component->assertSee($mine->recipient->displayName())->assertDontSeeHtml('data-test="accept-booking-');
});

test('organisers are never booked, so they only see what they sent', function () {
    $organiser = User::factory()->organiser()->create();
    $mine = BookingRequest::factory()->for(Event::factory()->for($organiser, 'owner'))->create();

    $component = Livewire::actingAs($organiser)->test(Index::class);

    expect($component->instance()->activeTab())->toBe('sent')
        ->and($component->get('bookings')['awaiting']->pluck('id')->all())->toBe([$mine->id]);
    $component->assertDontSeeHtml('data-test="bookings-tab-received"');
});

test('an artist accepting is booked and its day is no longer free', function () {
    $artist = User::factory()->artist()->create();
    $booking = BookingRequest::factory()->for($artist, 'recipient')
        ->for(Event::factory()->state(['user_id' => User::factory()->venue(), 'starts_at' => '2026-10-17 20:00']))
        ->create();
    Availability::factory()->for($artist)->create(['date' => '2026-10-17']);
    Availability::factory()->for($artist)->create(['date' => '2026-10-18']);

    Livewire::actingAs($artist)->test(Index::class)->call('accept', $booking->id)->assertHasNoErrors();

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Accepted)
        ->responded_at->not->toBeNull()
        ->and($artist->availabilities()->pluck('date')->map->format('Y-m-d')->all())->toBe(['2026-10-18']);

    Notification::assertSentTo($booking->event->owner, BookingAnswered::class);
});

test('a venue accepting becomes the event venue', function () {
    $venue = User::factory()->venue()->create(['name' => 'The Loft']);
    $venue->venueProfile->update(['location' => 'Long Street, Cape Town']);
    $booking = BookingRequest::factory()->for(Event::factory()->for(User::factory()->artist(), 'owner'))->for($venue, 'recipient')->create();
    Availability::factory()->for($venue)->create(['date' => $booking->event->starts_at->format('Y-m-d')]);

    Livewire::actingAs($venue)->test(Index::class)->call('accept', $booking->id)->assertHasNoErrors();

    expect($booking->event->fresh())
        ->venue_profile_id->toBe($venue->venueProfile->id)
        ->location->toBe('The Loft, Long Street, Cape Town')
        ->and($venue->availabilities()->count())->toBe(0);

    Notification::assertSentTo($booking->event->owner, BookingAnswered::class);
});

test('declining tells the event owner', function () {
    $artist = User::factory()->artist()->create();
    $booking = BookingRequest::factory()->for($artist, 'recipient')->create();

    Livewire::actingAs($artist)->test(Index::class)->call('decline', $booking->id)->assertHasNoErrors();

    expect($booking->fresh()->status)->toBe(BookingStatus::Declined);
    Notification::assertSentTo($booking->event->owner, BookingAnswered::class);
});

test('a request past its deadline can no longer be answered', function () {
    $artist = User::factory()->artist()->create();
    $booking = BookingRequest::factory()->for($artist, 'recipient')->overdue()->create();

    Livewire::actingAs($artist)->test(Index::class)->call('accept', $booking->id)->assertHasErrors(['booking']);

    expect($booking->fresh()->status)->toBe(BookingStatus::Requested);
    Notification::assertNothingSent();
});

test('nobody can accept two bookings on the same day', function () {
    $artist = User::factory()->artist()->create();
    $venueEvent = fn () => Event::factory()->state(['user_id' => User::factory()->venue(), 'starts_at' => '2026-10-17 20:00']);
    BookingRequest::factory()->for($artist, 'recipient')->for($venueEvent())->accepted()->create();
    $second = BookingRequest::factory()->for($artist, 'recipient')->for($venueEvent())->create();

    Livewire::actingAs($artist)->test(Index::class)
        ->call('accept', $second->id)
        ->assertHasErrors(['booking'])
        ->assertSee('already booked');

    expect($second->fresh()->status)->toBe(BookingStatus::Requested);
});

test('only the account that was asked can answer', function () {
    $booking = BookingRequest::factory()->create();

    Livewire::actingAs(User::factory()->artist()->create())
        ->test(Index::class)
        ->call('accept', $booking->id)
        ->assertNotFound();

    expect($booking->fresh()->status)->toBe(BookingStatus::Requested);
});

test('the sidebar counts requests waiting for a reply plus unseen booking updates', function () {
    $artist = User::factory()->artist()->create();
    BookingRequest::factory()->for($artist, 'recipient')->count(2)->create();
    BookingRequest::factory()->for($artist, 'recipient')->overdue()->create();
    $notify = fn (string $type) => $artist->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'data' => ['summary' => 'Update'],
    ]);
    $notify(BookingRequested::class); // already counted as a request waiting for a reply
    $notify(BookingAnswered::class);

    expect($artist->bookingsNeedingAttention())->toBe(3);

    Livewire::actingAs($artist)->test(Index::class);

    expect($artist->bookingsNeedingAttention())->toBe(2);
});

test('artists and venues with requests waiting are told on their dashboard', function (string $state) {
    $user = User::factory()->{$state}()->create();
    BookingRequest::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('You have 1 booking request waiting for your reply.');
})->with(['artist', 'venue']);

test('an artist cannot accept a booking on a day it is playing its own event', function () {
    $artist = User::factory()->artist()->create();
    Event::factory()->for($artist, 'owner')->create(['starts_at' => '2026-10-17 18:00']);
    $booking = BookingRequest::factory()->for($artist, 'recipient')
        ->for(Event::factory()->state(['user_id' => User::factory()->venue(), 'starts_at' => '2026-10-17 21:00']))
        ->create();

    Livewire::actingAs($artist)->test(Index::class)
        ->call('accept', $booking->id)
        ->assertHasErrors(['booking'])
        ->assertSee('playing your own event');

    expect($booking->fresh()->status)->toBe(BookingStatus::Requested);
});
