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
        'form.band_slots' => '1',
        'form.status' => EventStatus::Published->value,
        ...$overrides,
    ];
}

test('a new event that is sending requests has to start as a draft', function () {
    $band = User::factory()->band()->create();
    Availability::factory()->for($band)->create(['date' => '2026-10-24']);

    Livewire::actingAs(User::factory()->organiser()->create())
        ->test(ManageEvent::class)
        ->set(publishableEvent())
        ->set('bandIds', [(string) $band->id])
        ->call('save')
        ->assertHasErrors(['form.status'])
        ->set('form.status', EventStatus::Draft->value)
        ->call('save')
        ->assertHasNoErrors();
});

test('a band playing its own gig with nobody else to confirm can publish straight away', function () {
    $band = User::factory()->band()->create();

    Livewire::actingAs($band)
        ->test(ManageEvent::class)
        ->set(publishableEvent())
        ->call('save')
        ->assertHasNoErrors();

    expect($band->events()->sole()->status)->toBe(EventStatus::Published);
});

test('a venue cannot publish an event until its bands are confirmed', function () {
    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set(publishableEvent())
        ->call('save')
        ->assertHasErrors(['form.status']);
});

test('a draft can be published once every band slot is confirmed and the venue has accepted', function () {
    $organiser = User::factory()->organiser()->create();
    $event = Event::factory()->for($organiser, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'band_slots' => 2]);
    $bandA = BookingRequest::factory()->for($event)->accepted()->create();
    $bandB = BookingRequest::factory()->for($event)->create();
    $venue = BookingRequest::factory()->for($event)->for(User::factory()->venue(), 'recipient')->create();

    $component = Livewire::actingAs($organiser)->test(ManageEvent::class, ['event' => $event]);

    expect($component->instance()->publishBlockers())->toBe([
        '1 of 2 bands confirmed.',
        'Waiting for '.$venue->recipient->displayName().' to confirm.',
    ]);
    $component->assertSeeHtml('data-test="publish-blockers"')
        ->set('form.status', EventStatus::Published->value)
        ->call('save')
        ->assertHasErrors(['form.status']);

    $bandB->update(['status' => 'accepted']);
    $venue->update(['status' => 'accepted']);
    $event->update(['venue_profile_id' => $venue->recipient->venueProfile->id]);

    Livewire::actingAs($organiser)
        ->test(ManageEvent::class, ['event' => $event->fresh()])
        ->assertDontSeeHtml('data-test="publish-blockers"')
        ->set('form.status', EventStatus::Published->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->status)->toBe(EventStatus::Published)
        ->and($bandA->fresh()->status->value)->toBe('accepted');
});

test('a declined band reopens its slot, so the draft is no longer ready', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create(['starts_at' => '2026-10-24 20:00', 'band_slots' => 1]);
    BookingRequest::factory()->for($event)->declined()->create();

    expect($event->isReadyToPublish())->toBeFalse()
        ->and($event->publishBlockers())->toBe(['0 of 1 band confirmed.']);
});

test('drafts that are ready are flagged on the events list', function () {
    $venue = User::factory()->venue()->create();
    $ready = Event::factory()->for($venue, 'owner')->draft()->create(['title' => 'Ready One', 'band_slots' => 1]);
    BookingRequest::factory()->for($ready)->accepted()->create();
    Event::factory()->for($venue, 'owner')->draft()->create(['title' => 'Not Yet', 'band_slots' => 2]);

    Livewire::actingAs($venue)
        ->test(Index::class)
        ->set('tab', 'draft')
        ->assertSeeHtml('data-test="ready-to-publish"')
        ->assertSee('Ready One')
        ->assertSee('Not Yet');

    expect(substr_count(Livewire::actingAs($venue)->test(Index::class)->set('tab', 'draft')->html(), 'data-test="ready-to-publish"'))->toBe(1);
});

test('the acceptance that completes a draft tells the owner to publish it', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->draft()->create(['band_slots' => 1]);
    $booking = BookingRequest::factory()->for($event)->accepted()->create();

    $mail = (new BookingAnswered($booking))->toMail($venue);

    expect($mail->actionText)->toBe('Publish your event')
        ->and($mail->actionUrl)->toBe(route('events.edit', $event));
});

test('an already published event can still be saved', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00', 'band_slots' => 3]);

    Livewire::actingAs($venue)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('form.title', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();
});
