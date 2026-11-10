<?php

use App\Livewire\Events\ManageEvent;
use App\Livewire\Profile\EditProfile;
use App\Models\Availability;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('an artist can set a booking fee and whether it plays for door splits', function () {
    $artist = User::factory()->artist()->create();

    Livewire::actingAs($artist)
        ->test(EditProfile::class)
        ->set(['form.booking_fee' => '3500', 'form.accepts_door_split' => true])
        ->call('save')
        ->assertHasNoErrors();

    expect($artist->artistProfile->fresh())
        ->booking_fee->toBe(3500)
        ->accepts_door_split->toBeTrue();
});

test('both booking terms are optional', function () {
    $artist = User::factory()->artist()->create();

    Livewire::actingAs($artist)
        ->test(EditProfile::class)
        ->set(['form.booking_fee' => '', 'form.accepts_door_split' => false])
        ->call('save')
        ->assertHasNoErrors();

    expect($artist->artistProfile->fresh())
        ->booking_fee->toBeNull()
        ->accepts_door_split->toBeFalse()
        ->and($artist->artistProfile->fresh()->bookingTerms())->toBe('Fee on request');
});

test('the booking fee must be a whole, positive amount', function (string $fee) {
    Livewire::actingAs(User::factory()->artist()->create())
        ->test(EditProfile::class)
        ->set('form.booking_fee', $fee)
        ->call('save')
        ->assertHasErrors(['form.booking_fee']);
})->with(['-100', 'lots', '12.5']);

test('booking terms show on the artist profile', function () {
    $artist = User::factory()->artist()->create();
    $artist->artistProfile->update(['booking_fee' => 2500, 'accepts_door_split' => true]);

    $this->actingAs(User::factory()->venue()->create())
        ->get(route('profiles.show', $artist))
        ->assertOk()
        ->assertSee('Booking Terms')
        ->assertSee('R2,500')
        ->assertSee('Open to splits');
});

test('hosts see each artist\'s terms when picking artists', function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $artist = User::factory()->artist()->create();
    $artist->artistProfile->update(['booking_fee' => 4000, 'accepts_door_split' => true]);
    Availability::factory()->for($artist)->create(['date' => '2026-10-17']);

    Livewire::actingAs(User::factory()->venue()->create())
        ->test(ManageEvent::class)
        ->set('form.date', '2026-10-17')
        ->call('next')
        ->assertSee('R4,000 fee · Open to door / ticket splits');

    Carbon::setTestNow();
});
