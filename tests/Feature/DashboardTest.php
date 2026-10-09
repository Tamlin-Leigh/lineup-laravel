<?php

use App\Enums\UserRole;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('unverified users are asked to verify their email first', function () {
    $this->actingAs(User::factory()->band()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('a venue sees its venue dashboard', function () {
    $user = User::factory()->venue()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sound &amp; Equipment', false)
        ->assertSee($user->venueProfile->location)
        ->assertSee($user->venueProfile->manager_email);
});

test('a band sees its band dashboard', function () {
    $user = User::factory()->band()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, '.$user->bandProfile->band_name)
        ->assertSee('Booked Before Year End')
        ->assertSee('No upcoming events booked yet.');
});

test('an organiser sees its organiser dashboard', function () {
    $user = User::factory()->organiser()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($user->organiserProfile->org_name)
        ->assertSee($user->organiserProfile->contact_email);
});

test('accounts without a profile are prompted to finish it', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Venue]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Finish setting up your profile');
});

test('admins are sent to the admin overview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('the admin overview counts accounts by role and lists recent sign-ups', function () {
    User::factory()->venue()->count(2)->create();
    $band = User::factory()->band()->create(['name' => 'Newest Band Manager']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('roleCounts', fn ($counts) => $counts->all() === ['venue' => 2, 'band' => 1, 'organiser' => 0])
        ->assertSee($band->name);
});

test('only admins can open the admin overview', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
})->with(['venue', 'band', 'organiser']);
