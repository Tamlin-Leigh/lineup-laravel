<?php

use App\Enums\EventStatus;
use App\Livewire\Events\ManageEvent;
use App\Models\Availability;
use App\Models\User;
use App\Notifications\BookingRequested;
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

function freeForWizard(string $state, string $date): User
{
    $user = User::factory()->{$state}()->create();
    Availability::factory()->for($user)->create(['date' => $date]);

    return $user;
}

test('the steps follow what each role books', function (string $state, array $steps) {
    $component = Livewire::actingAs(User::factory()->{$state}()->create())->test(ManageEvent::class);

    expect(array_keys($component->instance()->steps()))->toBe($steps);
})->with([
    'venues skip where, as it is their venue' => ['venue', ['when', 'line-up', 'details']],
    'bands pick a venue' => ['band', ['when', 'where', 'line-up', 'details']],
    'organisers pick a venue' => ['organiser', ['when', 'where', 'line-up', 'details']],
]);

test('an organiser walks through the steps and books a venue and a band', function () {
    $venue = freeForWizard('venue', '2026-10-17');
    $band = freeForWizard('band', '2026-10-17');
    $organiser = User::factory()->organiser()->create();

    $component = Livewire::actingAs($organiser)
        ->test(ManageEvent::class)
        ->assertSee('When is it?')
        ->set(['form.date' => '2026-10-17', 'form.time' => '19:00'])
        ->call('next')
        ->assertSet('step', 1)
        ->assertSee($venue->displayName())
        ->assertSee('Somewhere else')
        ->set('venueId', (string) $venue->id)
        ->call('next')
        ->assertSet('step', 2)
        ->assertSee($band->displayName())
        ->set('bandIds', [(string) $band->id])
        ->call('next')
        ->assertSet('step', 3)
        ->assertSee('Booking Requests')
        ->set([
            'form.title' => 'Spring Social',
            'form.price' => '80',
            'form.status' => EventStatus::Draft->value,
        ])
        ->call('next')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $event = $organiser->events()->sole();

    expect($event->title)->toBe('Spring Social')
        ->and($event->location)->toBe($venue->name.', '.$venue->venueProfile->location)
        ->and($event->bookingRequests()->pluck('recipient_id')->sort()->values()->all())->toBe([$venue->id, $band->id]);

    Notification::assertSentTo([$venue, $band], BookingRequested::class);
});

test('each step is checked before moving on', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->test(ManageEvent::class)
        ->set('form.date', 'not-a-date')
        ->call('next')
        ->assertHasErrors(['form.date'])
        ->assertSet('step', 0)
        ->set('form.date', '2026-10-17')
        ->call('next')
        ->assertSet('step', 1)
        // Somewhere else needs a location.
        ->set(['venueId' => '', 'form.location' => ''])
        ->call('next')
        ->assertHasErrors(['form.location'])
        ->assertSet('step', 1)
        ->set('form.location', 'The Park, Durban')
        ->call('next')
        ->assertSet('step', 2);
});

test('a band sees itself on the line-up step as the host', function () {
    $band = User::factory()->band()->create();

    Livewire::actingAs($band)
        ->test(ManageEvent::class)
        ->set(['form.date' => '2026-10-17', 'form.location' => 'The Park'])
        ->call('next')
        ->call('next')
        ->assertSee('Host')
        ->assertSee($band->displayName());
});

test('back goes to the previous step, or out of the wizard from the first', function () {
    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->call('next')
        ->assertSet('step', 1)
        ->call('back')
        ->assertSet('step', 0)
        ->call('back')
        ->assertRedirect(route('events.index'));
});
