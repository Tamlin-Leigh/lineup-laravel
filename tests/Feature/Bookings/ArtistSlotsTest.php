<?php

use App\Actions\SendBookingRequests;
use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Livewire\Bookings\Index;
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
 * @return list<User>
 */
function artistsFreeOn(string $date, int $count): array
{
    return User::factory()->artist()->count($count)->create()
        ->each(fn (User $artist) => Availability::factory()->for($artist)->create(['date' => $date]))
        ->all();
}

/**
 * @param  list<User>  $artists
 * @return list<string>
 */
function idsOf(array $artists): array
{
    return array_map(fn (User $artist) => (string) $artist->id, $artists);
}

/**
 * @return array<string, string>
 */
function slottedEvent(string $slots): array
{
    return [
        'form.title' => 'Slot Night',
        'form.location' => 'The Park',
        'form.date' => '2026-10-24',
        'form.time' => '20:00',
        'form.price' => '0',
        'form.artist_slots' => $slots,
        'form.status' => EventStatus::Draft->value,
    ];
}

test('venues and organisers can ask as many artists as they have slots, and no more', function (string $state) {
    $artists = artistsFreeOn('2026-10-24', 4);

    Livewire::actingAs(User::factory()->{$state}()->create())
        ->test(ManageEvent::class)
        ->set(slottedEvent('3'))
        ->set('artistIds', idsOf($artists))
        ->call('save')
        ->assertHasErrors(['artistIds'])
        ->set('artistIds', idsOf(array_slice($artists, 0, 3)))
        ->call('save')
        ->assertHasNoErrors();

    expect(BookingRequest::count())->toBe(3);
})->with(['venue', 'organiser']);

test('an artist hosting takes one of the slots itself', function () {
    $artists = artistsFreeOn('2026-10-24', 5);
    $host = User::factory()->artist()->create();

    Livewire::actingAs($host)
        ->test(ManageEvent::class)
        ->set(slottedEvent('5'))
        ->set('artistIds', idsOf($artists))
        ->call('save')
        ->assertHasErrors(['artistIds' => 'There are only 4 artist slots left, so tick up to 4 artists.'])
        ->set('artistIds', idsOf(array_slice($artists, 0, 4)))
        ->call('save')
        ->assertHasNoErrors();

    $event = $host->events()->sole();

    expect($event->bookingRequests()->count())->toBe(4)
        ->and($event->openArtistSlots())->toBe(0);
});

test('the wizard checks the slots on the line-up step', function () {
    $artists = artistsFreeOn('2026-10-24', 2);

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set('form.date', '2026-10-24')
        ->call('next')
        ->set(['form.artist_slots' => '1', 'artistIds' => idsOf($artists)])
        ->call('next')
        ->assertHasErrors(['artistIds'])
        ->assertSet('step', 1)
        ->set('form.artist_slots', '2')
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

test('declined artists free their slot for someone new, but are not offered again', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 5]);
    [$first, $second, $third, $fourth, $fifth] = artistsFreeOn('2026-10-24', 5);
    BookingRequest::factory()->for($event)->for($first, 'recipient')->declined()->create();
    BookingRequest::factory()->for($event)->for($second, 'recipient')->declined()->create();
    foreach ([$third, $fourth, $fifth] as $artist) {
        BookingRequest::factory()->for($event)->for($artist, 'recipient')->create();
    }
    $replacements = artistsFreeOn('2026-10-24', 3);

    $component = Livewire::actingAs($venue)->test(ManageEvent::class, ['event' => $event]);

    expect($event->openArtistSlots())->toBe(2)
        ->and($component->get('freeArtists')->pluck('id')->all())->not->toContain($first->id)
        ->and($component->get('freeArtists')->pluck('id')->all())->not->toContain($second->id);

    $component->set('artistIds', idsOf($replacements))
        ->call('save')
        ->assertHasErrors(['artistIds'])
        ->set('artistIds', idsOf(array_slice($replacements, 0, 2)))
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->openArtistSlots())->toBe(0)
        ->and($event->bookingRequests()->where('status', BookingStatus::Declined)->count())->toBe(2);
});

test('the slots cannot drop below the artists already playing or asked', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 3]);
    BookingRequest::factory()->for($event)->accepted()->create();
    BookingRequest::factory()->for($event)->create();

    Livewire::actingAs($venue)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('form.artist_slots', '1')
        ->call('save')
        ->assertHasErrors(['form.artist_slots'])
        ->set('form.artist_slots', '2')
        ->call('save')
        ->assertHasNoErrors();
});

test('requests beyond the open slots are never sent, even if posted', function () {
    $event = Event::factory()->for(User::factory()->venue(), 'owner')->create(['starts_at' => '2026-10-24 20:00', 'artist_slots' => 2]);

    $sent = app(SendBookingRequests::class)->handle($event, idsOf(artistsFreeOn('2026-10-24', 4)));

    expect($sent)->toHaveCount(2);
});

test('the decline email points the owner at the event to book someone else', function () {
    $booking = BookingRequest::factory()->declined()->create();

    $mail = (new BookingAnswered($booking))->toMail($booking->event->owner);

    expect($mail->actionText)->toBe('Book another artist')
        ->and($mail->actionUrl)->toBe(route('events.edit', $booking->event));
});

test('the bookings page offers a replacement for a declined artist', function () {
    $venue = User::factory()->venue()->create();
    $declined = BookingRequest::factory()->for(Event::factory()->for($venue, 'owner'))->declined()->create();

    Livewire::actingAs($venue)
        ->test(Index::class)
        ->set('tab', 'sent')
        ->assertSeeHtml('data-test="replace-booking-'.$declined->id.'"');
});
