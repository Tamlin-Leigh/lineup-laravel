<?php

use App\Models\Availability;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('profiles show who someone is and when they are free', function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $band = User::factory()->band()->create();
    Availability::factory()->for($band)->create(['date' => '2026-10-10']);
    Event::factory()->for($band, 'owner')->create(['title' => 'Friday Late Show', 'starts_at' => '2026-10-16 21:00']);
    Event::factory()->for($band, 'owner')->draft()->create(['title' => 'Secret Draft', 'starts_at' => '2026-10-23 21:00']);

    $this->actingAs(User::factory()->venue()->create())
        ->get(route('profiles.show', $band))
        ->assertOk()
        ->assertSee($band->bandProfile->band_name)
        ->assertSee('Sat 10 Oct')
        ->assertSee('Friday Late Show')
        ->assertDontSee('Secret Draft');
});

test('profiles never show contact details', function (string $state) {
    $user = User::factory()->{$state}()->create(['phone' => '0821234567']);
    $profile = $user->venueProfile ?? $user->bandProfile ?? $user->organiserProfile;

    $response = $this->actingAs(User::factory()->band()->create())
        ->get(route('profiles.show', $user))
        ->assertOk()
        ->assertDontSee($user->email)
        ->assertDontSee('0821234567');

    foreach (['manager_email', 'manager_phone', 'contact_email', 'contact_number'] as $field) {
        if (filled($profile->{$field} ?? null)) {
            $response->assertDontSee($profile->{$field});
        }
    }
})->with(['venue', 'band', 'organiser']);

test('admins and unverified accounts have no public profile', function (User $user) {
    $this->actingAs(User::factory()->band()->create())
        ->get(route('profiles.show', $user))
        ->assertForbidden();
})->with([
    'admin' => fn () => User::factory()->admin()->create(),
    'unverified' => fn () => User::factory()->venue()->unverified()->create(),
]);

test('guests cannot view profiles', function () {
    $this->get(route('profiles.show', User::factory()->band()->create()))
        ->assertRedirect(route('login'));
});
