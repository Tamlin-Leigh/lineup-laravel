<?php

use App\Enums\UserRole;
use App\Models\Availability;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;

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

test("a band's stats and lists come from its published events", function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $user = User::factory()->band()->create();
    $owned = Event::factory()->for($user, 'owner');

    $owned->create(['title' => 'This Year', 'starts_at' => '2026-11-01 20:00']);
    $owned->create(['title' => 'Next Year', 'starts_at' => '2027-02-01 20:00']);
    $owned->draft()->create(['title' => 'Still a Draft', 'starts_at' => '2026-10-20 20:00']);
    $owned->past()->count(2)->create();
    Event::factory()->create(['title' => 'Another Band', 'starts_at' => '2026-10-10 20:00']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('bookedBeforeYearEnd', 1)
        ->assertViewHas('completedEvents', 2)
        ->assertSee('This Year')
        ->assertSee('Next Year')
        ->assertDontSee('Still a Draft')
        ->assertDontSee('Another Band');

    Carbon::setTestNow();
});

test('every role sees its upcoming free days on the dashboard', function (string $state) {
    Carbon::setTestNow('2026-10-08 09:00');
    $user = User::factory()->{$state}()->create();
    Availability::factory()->for($user)->create(['date' => '2026-10-02']);
    Availability::factory()->for($user)->create(['date' => '2026-10-09']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your Availability')
        ->assertSee('Fri 9 Oct')
        ->assertDontSee('Fri 2 Oct');

    Carbon::setTestNow();
})->with(['venue', 'band', 'organiser']);

test('the dashboard prompts users to mark free days', function () {
    $this->actingAs(User::factory()->band()->create())
        ->get(route('dashboard'))
        ->assertSee('Mark the days you are free so others can find you for a gig.');
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
