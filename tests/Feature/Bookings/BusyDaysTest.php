<?php

use App\Actions\UpdateAvailability;
use App\Enums\EventStatus;
use App\Livewire\Events\Calendar;
use App\Livewire\Events\ManageEvent;
use App\Models\Availability;
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

/**
 * @return list<string>
 */
function freeDatesOf(User $user): array
{
    return $user->availabilities()->orderBy('date')->pluck('date')->map->format('Y-m-d')->all();
}

/**
 * @return array<string, string>
 */
function hostedEventOn(string $date, string $status = 'published'): array
{
    return [
        'form.title' => 'Our Own Show',
        'form.location' => 'The Loft, Cape Town',
        'form.date' => $date,
        'form.time' => '20:00',
        'form.price' => '100',
        'form.status' => $status,
    ];
}

test('hosting an event takes the host off the free list for that day', function (string $state, string $status) {
    $host = User::factory()->{$state}()->create();
    Availability::factory()->for($host)->create(['date' => '2026-10-17']);
    Availability::factory()->for($host)->create(['date' => '2026-10-18']);

    Livewire::actingAs($host)
        ->test(ManageEvent::class)
        ->set(hostedEventOn('2026-10-17', $status))
        ->call('save')
        ->assertHasNoErrors();

    expect(freeDatesOf($host))->toBe(['2026-10-18']);
})->with(['band', 'venue', 'organiser'])->with([EventStatus::Draft->value]);

test('moving an event to another day takes that day off the free list too', function () {
    $band = User::factory()->band()->create();
    $event = Event::factory()->for($band, 'owner')->create(['starts_at' => '2026-10-17 20:00']);
    Availability::factory()->for($band)->create(['date' => '2026-10-24']);

    Livewire::actingAs($band)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('form.date', '2026-10-24')
        ->call('save')
        ->assertHasNoErrors();

    expect(freeDatesOf($band))->toBe([]);
});

test('a day you are hosting or booked for cannot be marked free', function () {
    $band = User::factory()->band()->create();
    Event::factory()->for($band, 'owner')->create(['starts_at' => '2026-10-17 20:00']);
    BookingRequest::factory()->for($band, 'recipient')
        ->for(Event::factory()->state(['user_id' => User::factory()->venue(), 'starts_at' => '2026-10-18 20:00']))
        ->accepted()
        ->create();
    Event::factory()->for($band, 'owner')->cancelled()->create(['starts_at' => '2026-10-16 20:00']);

    $availability = app(UpdateAvailability::class);

    expect($availability->toggle($band, Carbon::parse('2026-10-17')))->toBeFalse()
        ->and($availability->markFree($band, [Carbon::parse('2026-10-16'), Carbon::parse('2026-10-17'), Carbon::parse('2026-10-18'), Carbon::parse('2026-10-19')]))->toBe(2)
        ->and(freeDatesOf($band))->toBe(['2026-10-16', '2026-10-19']);
});

test('the calendar shows events you are booked for, linking to your bookings', function () {
    $band = User::factory()->band()->create();
    $own = Event::factory()->for($band, 'owner')->create(['title' => 'Our Own Show', 'starts_at' => '2026-10-17 20:00']);
    $booked = Event::factory()->for(User::factory()->venue(), 'owner')->create(['title' => 'Booked Gig', 'starts_at' => '2026-10-24 20:00']);
    BookingRequest::factory()->for($band, 'recipient')->for($booked)->accepted()->create();
    $declined = Event::factory()->for(User::factory()->venue(), 'owner')->create(['title' => 'Declined Gig', 'starts_at' => '2026-10-25 20:00']);
    BookingRequest::factory()->for($band, 'recipient')->for($declined)->declined()->create();

    Livewire::actingAs($band)
        ->test(Calendar::class)
        ->assertSee('Our Own Show')
        ->assertSee('Booked Gig')
        ->assertDontSee('Declined Gig')
        ->assertSeeHtml(route('events.edit', $own))
        ->assertSeeHtml(route('bookings.index'))
        ->assertDontSeeHtml(route('events.edit', $booked));
});

test('the calendar shows requests waiting for your reply', function () {
    $band = User::factory()->band()->create();
    $waiting = BookingRequest::factory()->for($band, 'recipient')
        ->for(Event::factory()->state(['user_id' => User::factory()->venue(), 'title' => 'Open Mic', 'starts_at' => '2026-10-17 19:00']))
        ->create();

    Livewire::actingAs($band)
        ->test(Calendar::class)
        ->assertSeeHtml('data-test="calendar-request-'.$waiting->id.'"')
        ->assertSee('Request waiting for your reply');
});
