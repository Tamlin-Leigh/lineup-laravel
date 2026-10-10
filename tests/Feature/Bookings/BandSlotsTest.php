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
function bandsFreeOn(string $date, int $count): array
{
    return User::factory()->band()->count($count)->create()
        ->each(fn (User $band) => Availability::factory()->for($band)->create(['date' => $date]))
        ->all();
}

/**
 * @param  list<User>  $bands
 * @return list<string>
 */
function idsOf(array $bands): array
{
    return array_map(fn (User $band) => (string) $band->id, $bands);
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
        'form.band_slots' => $slots,
        'form.status' => EventStatus::Draft->value,
    ];
}

test('venues and organisers can ask as many bands as they have slots, and no more', function (string $state) {
    $bands = bandsFreeOn('2026-10-24', 4);

    Livewire::actingAs(User::factory()->{$state}()->create())
        ->test(ManageEvent::class)
        ->set(slottedEvent('3'))
        ->set('bandIds', idsOf($bands))
        ->call('save')
        ->assertHasErrors(['bandIds'])
        ->set('bandIds', idsOf(array_slice($bands, 0, 3)))
        ->call('save')
        ->assertHasNoErrors();

    expect(BookingRequest::count())->toBe(3);
})->with(['venue', 'organiser']);

test('a band hosting takes one of the slots itself', function () {
    $bands = bandsFreeOn('2026-10-24', 5);
    $host = User::factory()->band()->create();

    Livewire::actingAs($host)
        ->test(ManageEvent::class)
        ->set(slottedEvent('5'))
        ->set('bandIds', idsOf($bands))
        ->call('save')
        ->assertHasErrors(['bandIds' => 'There are only 4 band slots left, so tick up to 4 bands.'])
        ->set('bandIds', idsOf(array_slice($bands, 0, 4)))
        ->call('save')
        ->assertHasNoErrors();

    $event = $host->events()->sole();

    expect($event->bookingRequests()->count())->toBe(4)
        ->and($event->openBandSlots())->toBe(0);
});

test('the wizard checks the slots on the line-up step', function () {
    $bands = bandsFreeOn('2026-10-24', 2);

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set('form.date', '2026-10-24')
        ->call('next')
        ->set(['form.band_slots' => '1', 'bandIds' => idsOf($bands)])
        ->call('next')
        ->assertHasErrors(['bandIds'])
        ->assertSet('step', 1)
        ->set('form.band_slots', '2')
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

test('declined bands free their slot for someone new, but are not offered again', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00', 'band_slots' => 5]);
    [$first, $second, $third, $fourth, $fifth] = bandsFreeOn('2026-10-24', 5);
    BookingRequest::factory()->for($event)->for($first, 'recipient')->declined()->create();
    BookingRequest::factory()->for($event)->for($second, 'recipient')->declined()->create();
    foreach ([$third, $fourth, $fifth] as $band) {
        BookingRequest::factory()->for($event)->for($band, 'recipient')->create();
    }
    $replacements = bandsFreeOn('2026-10-24', 3);

    $component = Livewire::actingAs($venue)->test(ManageEvent::class, ['event' => $event]);

    expect($event->openBandSlots())->toBe(2)
        ->and($component->get('freeBands')->pluck('id')->all())->not->toContain($first->id)
        ->and($component->get('freeBands')->pluck('id')->all())->not->toContain($second->id);

    $component->set('bandIds', idsOf($replacements))
        ->call('save')
        ->assertHasErrors(['bandIds'])
        ->set('bandIds', idsOf(array_slice($replacements, 0, 2)))
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->openBandSlots())->toBe(0)
        ->and($event->bookingRequests()->where('status', BookingStatus::Declined)->count())->toBe(2);
});

test('the slots cannot drop below the bands already playing or asked', function () {
    $venue = User::factory()->venue()->create();
    $event = Event::factory()->for($venue, 'owner')->create(['starts_at' => '2026-10-24 20:00', 'band_slots' => 3]);
    BookingRequest::factory()->for($event)->accepted()->create();
    BookingRequest::factory()->for($event)->create();

    Livewire::actingAs($venue)
        ->test(ManageEvent::class, ['event' => $event])
        ->set('form.band_slots', '1')
        ->call('save')
        ->assertHasErrors(['form.band_slots'])
        ->set('form.band_slots', '2')
        ->call('save')
        ->assertHasNoErrors();
});

test('requests beyond the open slots are never sent, even if posted', function () {
    $event = Event::factory()->for(User::factory()->venue(), 'owner')->create(['starts_at' => '2026-10-24 20:00', 'band_slots' => 2]);

    $sent = app(SendBookingRequests::class)->handle($event, idsOf(bandsFreeOn('2026-10-24', 4)));

    expect($sent)->toHaveCount(2);
});

test('the decline email points the owner at the event to book someone else', function () {
    $booking = BookingRequest::factory()->declined()->create();

    $mail = (new BookingAnswered($booking))->toMail($booking->event->owner);

    expect($mail->actionText)->toBe('Book another band')
        ->and($mail->actionUrl)->toBe(route('events.edit', $booking->event));
});

test('the bookings page offers a replacement for a declined band', function () {
    $venue = User::factory()->venue()->create();
    $declined = BookingRequest::factory()->for(Event::factory()->for($venue, 'owner'))->declined()->create();

    Livewire::actingAs($venue)
        ->test(Index::class)
        ->set('tab', 'sent')
        ->assertSeeHtml('data-test="replace-booking-'.$declined->id.'"');
});
