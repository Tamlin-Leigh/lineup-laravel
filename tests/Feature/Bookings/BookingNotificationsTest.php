<?php

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use App\Notifications\BookingAnswered;
use App\Notifications\BookingExpired;
use App\Notifications\BookingRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

afterEach(function () {
    Carbon::setTestNow();
});

test('the request email has the event details and a deadline, but not the sender address', function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $venue = User::factory()->venue()->create(['name' => 'The Loft', 'email' => 'bookings@theloft.test']);
    $booking = BookingRequest::factory()
        ->for(Event::factory()->for($venue, 'owner')->state(['title' => 'Saturday Showcase', 'starts_at' => '2026-10-17 20:00', 'location' => 'Long Street']))
        ->create(['message' => 'Bring your own kit.']);

    $html = (string) (new BookingRequested($booking))->toMail($booking->recipient)->render();

    expect($html)->toContain('The Loft')
        ->toContain('Saturday Showcase')
        ->toContain('Sat 17 Oct 2026 at 20:00')
        ->toContain('Long Street')
        ->toContain('Bring your own kit.')
        ->toContain('Tue 13 Oct at 09:00')
        ->toContain(route('bookings.index'))
        ->not->toContain('bookings@theloft.test');
});

test('booking updates are emailed and kept for the in-app bookings page', function () {
    $booking = BookingRequest::factory()->create();

    $notification = new BookingRequested($booking);

    expect($notification->via($booking->recipient))->toBe(['mail', 'database'])
        ->and($notification->toArray($booking->recipient))->toMatchArray([
            'booking_request_id' => $booking->id,
            'event_id' => $booking->event_id,
        ]);
});

test('the reply email says whether the artist accepted', function (string $state, string $expected) {
    $booking = BookingRequest::factory()->{$state}()->create();

    expect((new BookingAnswered($booking))->toMail($booking->event->owner)->subject)->toContain($expected);
})->with([
    'accepted' => ['accepted', 'accepted your booking request'],
    'declined' => ['declined', 'declined your booking request'],
]);

test('unanswered requests expire on schedule and the event owner is told', function () {
    Notification::fake();
    $overdue = BookingRequest::factory()->overdue()->create();
    $waiting = BookingRequest::factory()->create();

    $this->artisan('bookings:expire')->expectsOutput('1 booking request expired.')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($waiting->fresh()->status)->toBe(BookingStatus::Requested);

    Notification::assertSentTo($overdue->event->owner, BookingExpired::class);
    Notification::assertNotSentTo($waiting->event->owner, BookingExpired::class);
});

test('a request reads as expired once its deadline passes, before the schedule stores it', function () {
    $booking = BookingRequest::factory()->overdue()->create();

    expect($booking->isAwaitingReply())->toBeFalse()
        ->and($booking->currentStatus())->toBe(BookingStatus::Expired)
        ->and(BookingRequest::awaitingReply()->count())->toBe(0);
});

test('venues are asked to host rather than play', function () {
    $booking = BookingRequest::factory()->toVenue()->create();

    expect((string) (new BookingRequested($booking))->toMail($booking->recipient)->render())->toContain('at your venue');

    $booking->update(['status' => BookingStatus::Accepted]);

    expect((string) (new BookingAnswered($booking))->toMail($booking->event->owner)->render())->toContain('will host');
});
