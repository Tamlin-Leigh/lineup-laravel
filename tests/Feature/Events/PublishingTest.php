<?php

use App\Enums\EventStatus;
use App\Livewire\Events\Index;
use App\Livewire\Events\ManageEvent;
use App\Models\Availability;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BookingAnswered;
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

/**
 * @return array<string, string>
 */
function publishableEvent(array $overrides = []): array
{
    return [
        'form.title' => 'Big Night',
        'form.location' => 'The Park',
        'form.date' => '2026-10-24',
        'form.time' => '20:00',
        'form.price' => '0',
        'form.artist_slots' => '1',
        'form.status' => EventStatus::Published->value,
        ...$overrides,
    ];
}

test('a new event that is sending requests has to start as a draft', function () {
    $artist = User::factory()->artist()->create();
    Availability::factory()->for($artist)->create(['date' => '2026-10-24']);

    Livewire::actingAs(User::factory()->organiser()->create())
        ->test(ManageEvent::class)
        ->set(publishableEvent())
        ->set('artistIds', [(string) $artist->id])
        ->call('save')
        ->assertHasErrors(['form.status'])
        ->set('form.status', EventStatus::Draft->value)
        ->call('save')
        ->assertHasNoErrors();
});

test('an artist playing its own gig with nobody else to confirm can publish straight away', function () {
    $artist = User::factory()->artist()->create();

    Livewire::actingAs($artist)
        ->test(ManageEvent::class)
        ->set(publishableEvent())
        ->call('save')
        ->assertHasNoErrors();

    expect($artist->events()->sole()->status)->toBe(EventStatus::Published);
});

test('a venue cannot publish an event until its artists are confirmed', function () {
    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set(publishableEvent())
        ->call('save')
        ->assertHasErrors(['form.status']);
});

test('a draft can be published once every artist slot is confirmed and the venue has accepted', function () {
    $organiser = User::factory()->organiser()->create();
    $event = Event::factory()->for($organiser, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 2]);
    $artistA = BookingRequest::factory()->for($event)->accepted()->create();
    $artistB = BookingRequest::factory()->for($event)->create();
    $venue = BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->create();

    $component = Livewire::actingAs($organiser)->test(ManageEvent::class, ['event' => $event]);

    expect($component->instance()->publishBlockers())->toBe([
        '1 of 2 artists confirmed.',
        'Waiting for '.$venue->recipient->displayName().' to confirm.',
    ]);
    $component->assertSeeHtml('data-test="publish-blockers"')
        ->set('form.status', EventStatus::Published->value)
        ->call('save')
        ->assertHasErrors(['form.status']);

    $artistB->update(['status' => 'accepted']);
    $venue->update(['status' => 'accepted']);
    $event->update(['venue_profile_id' => $venue->recipient->venueProfile->id]);

    Livewire::actingAs($organiser)
        ->test(ManageEvent::class, ['event' => $event->fresh()])
        ->assertDontSeeHtml('data-test="publish-blockers"')
        ->set('form.status', EventStatus::Published->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->status)->toBe(EventStatus::Published)
        ->and($artistA->fresh()->status->value)->toBe('accepted');
});

test('a declined artist reopens its slot, so the draft is no longer ready', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 1]);
    BookingRequest::factory()->for($event)->declined()->create();

    expect($event->isReadyToPublish())->toBeFalse()
        ->and($event->publishBlockers())->toBe(['0 of 1 artist confirmed.']);
});

test('drafts that are ready are flagged on the events list', function () {
    $venue = User::factory()->venue()->create();
    $ready = Event::factory()->for($venue, 'owner')->draft()->create(['title' => 'Ready One', 'artist_slots' => 1]);
    BookingRequest::factory()->for($ready)->accepted()->create();
    Event::factory()->for($venue, 'owner')->draft()->create(['title' => 'Not Yet', 'artist_slots' => 2]);

    Livewire::actingAs($venue)
        ->test(Index::class)
        ->set('tab', 'draft')
        ->assertSeeHtml('data-test="ready-to-publish"')
        ->assertSee('Ready One')
        ->assertSee('Not Yet');

    expect(substr_count(Livewire::actingAs($venue)->test(Index::class)->set('tab', 'draft')->html(), 'data-test="ready-to-publish"'))->toBe(1);
});

test('the owner can publish a ready draft straight from the events list', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 1]);
    BookingRequest::factory()->for($event)->accepted()->create();

    Livewire::actingAs($venue)
        ->test(Index::class)
        ->set('tab', 'draft')
        ->assertSeeHtml('data-test="publish-event"')
        ->call('publish', $event->id)
        ->assertDontSeeHtml('data-test="publish-event"');

    expect($event->fresh()->status)->toBe(EventStatus::Published);
});

test('only ready, upcoming drafts get a publish button', function () {
    $venue = User::factory()->venue()->create();
    $notReady = Event::factory()->for($venue, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 2]);
    BookingRequest::factory()->for($notReady)->accepted()->create();
    $past = Event::factory()->for($venue, 'owner')->draft()->create(['starts_at' => '2026-10-01 20:00', 'artist_slots' => 1]);
    BookingRequest::factory()->for($past)->accepted()->create();

    Livewire::actingAs($venue)
        ->test(Index::class)
        ->set('tab', 'draft')
        ->assertDontSeeHtml('data-test="publish-event"');
});

test('a draft that stopped being ready is not published from the list', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 2]);
    BookingRequest::factory()->for($event)->accepted()->create();

    Livewire::actingAs($venue)->test(Index::class)->call('publish', $event->id);

    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});

test('someone else\'s draft cannot be published from the list', function () {
    $event = Event::factory()->draft()->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 1]);
    BookingRequest::factory()->for($event)->accepted()->create();

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(Index::class)
        ->call('publish', $event->id)
        ->assertNotFound();

    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});

test('the acceptance that completes a draft tells the owner to publish it', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create(['artist_slots' => 1]);
    $booking = BookingRequest::factory()->for($event)->accepted()->create();

    $mail = (new BookingAnswered($booking))->toMail($venue);

    expect($mail->actionText)->toBe('Publish your event')
        ->and($mail->actionUrl)->toBe(route('events.edit', $event));
});

test('an already published event can still be saved', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 3]);

    Livewire::actingAs($venue)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('form.title', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();
});
