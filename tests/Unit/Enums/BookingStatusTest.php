<?php

use App\Enums\BookingStatus;

test('a requested booking can be accepted, declined or cancelled', function (BookingStatus $next) {
    expect(BookingStatus::Requested->canTransitionTo($next))->toBeTrue();
})->with([BookingStatus::Accepted, BookingStatus::Declined, BookingStatus::Cancelled]);

test('an accepted booking can only be cancelled', function () {
    expect(BookingStatus::Accepted->canTransitionTo(BookingStatus::Cancelled))->toBeTrue()
        ->and(BookingStatus::Accepted->canTransitionTo(BookingStatus::Declined))->toBeFalse()
        ->and(BookingStatus::Accepted->canTransitionTo(BookingStatus::Requested))->toBeFalse();
});

test('declined and cancelled bookings are final', function (BookingStatus $final) {
    foreach (BookingStatus::cases() as $next) {
        expect($final->canTransitionTo($next))->toBeFalse();
    }
})->with([BookingStatus::Declined, BookingStatus::Cancelled]);
