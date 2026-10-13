<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

test('expired link sends the user back to the prompt to request a new one', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-expired');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('expired link for an already verified user just continues to the dashboard', function () {
    $user = User::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('opening a link while logged in as another account logs out and returns to it after login', function () {
    $owner = User::factory()->unverified()->create();
    $other = User::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $owner->id, 'hash' => sha1($owner->email)],
    );

    $this->actingAs($other)->get($verificationUrl)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, $owner->email))
        ->assertSessionHas('url.intended', $verificationUrl);

    $this->assertGuest();
    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect($verificationUrl);

    $this->get($verificationUrl)->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($owner->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('expired link opened as another account logs out without returning to it', function () {
    $owner = User::factory()->unverified()->create();
    $other = User::factory()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $owner->id, 'hash' => sha1($owner->email)],
    );

    $this->actingAs($other)->get($verificationUrl)
        ->assertRedirect(route('login'))
        ->assertSessionMissing('url.intended');

    $this->assertGuest();
});

test('link with a tampered signature is still treated as expired, not verified', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl.'0')
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('invalid hash with a valid signature is forbidden', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl)->assertForbidden();
});
