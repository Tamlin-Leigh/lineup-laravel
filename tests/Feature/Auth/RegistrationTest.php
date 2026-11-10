<?php

use App\Enums\EventDay;
use App\Enums\LinkKind;
use App\Enums\LinkPlatform;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A component that has passed the shared account + personal steps for the given role.
 */
function registerAs(UserRole $role): Testable
{
    return Livewire::test(Register::class)
        ->set([
            'email' => 'Sam@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->call('next')
        ->assertHasNoErrors()
        ->set([
            'form.name' => 'Sam Smith',
            'form.phone' => '+27821234567',
            'role' => $role->value,
        ])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
}

/**
 * @return array<string, string>
 */
function managerDetails(): array
{
    return [
        'form.manager_name' => 'Morgan Lee',
        'form.manager_phone' => '0831234567',
        'form.manager_email' => 'morgan@example.com',
    ];
}

test('registration screen can be rendered', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSeeLivewire(Register::class);
});

test('signed in users cannot see the registration screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect();
});

test('the account step validates email and password before moving on', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'not-an-email', 'password' => 'password', 'password_confirmation' => 'different'])
        ->call('next')
        ->assertHasErrors(['email' => 'email', 'password' => 'confirmed'])
        ->assertSet('step', 0);
});

test('an email that is already registered is rejected', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Register::class)
        ->set(['email' => 'Taken@Example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->assertHasErrors(['email' => 'unique']);
});

test('the personal step requires a valid phone number and a role', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => '12'])
        ->call('next')
        ->assertHasErrors(['form.phone', 'role'])
        ->assertSet('step', 1);
});

test('phone numbers are stored as digits and a leading plus', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => ' +27 (82) 123-4567 ', 'role' => UserRole::Organiser->value])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('form.phone', '+27821234567');
});

test('admin cannot be chosen at sign-up', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => '0821234567', 'role' => UserRole::Admin->value])
        ->call('next')
        ->assertHasErrors(['role'])
        ->assertSet('step', 1);
});

test('steps cannot be skipped from the browser', function () {
    Livewire::test(Register::class)->set('step', 4);
})->throws(CannotUpdateLockedPropertyException::class);

test('each role gets its own steps', function (UserRole $role, array $labels) {
    expect(registerAs($role)->instance()->steps())->toBe($labels);
})->with([
    'venue' => [UserRole::Venue, ['account' => 'Account Details', 'personal' => 'Personal Info', 'manager' => 'Manager Details', 'venue' => 'Venue Details', 'event-days' => 'Event Preferences']],
    'artist' => [UserRole::Artist, ['account' => 'Account Details', 'personal' => 'Personal Info', 'manager' => 'Manager Details', 'artist' => 'Artist Details', 'links' => 'Links']],
    'organiser' => [UserRole::Organiser, ['account' => 'Account Details', 'personal' => 'Personal Info', 'organiser' => 'Organiser Details']],
]);

test('going back keeps what was entered', function () {
    registerAs(UserRole::Artist)
        ->call('back')
        ->assertSet('step', 1)
        ->assertSet('form.name', 'Sam Smith');
});

test('a venue can register', function () {
    Event::fake([Registered::class]);

    registerAs(UserRole::Venue)
        ->set(managerDetails())
        ->call('next')
        ->assertHasNoErrors()
        ->set([
            'form.capacity' => '250',
            'form.location' => '12 Long Street, Cape Town',
            'form.region' => 'cape-town',
            'form.min_door_charge' => '80',
            'form.sound_engineer' => SoundEngineer::InHouse->value,
            'form.sound_engineer_name' => 'Alex',
            'form.has_own_equipment' => true,
            'form.equipment_details' => 'PA and drum kit',
        ])
        ->set('form.social_links', [['platform' => LinkPlatform::Instagram->value, 'url' => 'https://instagram.com/longstreet']])
        ->call('next')
        ->assertHasNoErrors()
        ->set('form.event_days', [EventDay::Weekend->value])
        ->call('next')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();

    expect($user)
        ->email->toBe('sam@example.com')
        ->role->toBe(UserRole::Venue)
        ->phone->toBe('+27821234567')
        ->and($user->venueProfile)
        ->capacity->toBe(250)
        ->min_door_charge->toBe(80)
        ->sound_engineer->toBe(SoundEngineer::InHouse)
        ->sound_engineer_name->toBe('Alex')
        ->has_own_equipment->toBeTrue()
        ->equipment_details->toBe('PA and drum kit')
        ->and($user->venueProfile->event_days->all())->toBe([EventDay::Weekend])
        ->and($user->venueProfile->links()->sole())
        ->kind->toBe(LinkKind::Social)
        ->platform->toBe(LinkPlatform::Instagram)
        ->url->toBe('https://instagram.com/longstreet');

    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class);
});

test('a venue with an in-house sound engineer must name them', function () {
    registerAs(UserRole::Venue)
        ->set(managerDetails())
        ->call('next')
        ->set([
            'form.capacity' => '250',
            'form.location' => 'Cape Town',
            'form.region' => 'cape-town',
            'form.min_door_charge' => '0',
            'form.sound_engineer' => SoundEngineer::InHouse->value,
            'form.has_own_equipment' => false,
        ])
        ->call('next')
        ->assertHasErrors(['form.sound_engineer_name' => 'required_if'])
        ->assertSet('step', 3);
});

test('a venue must pick at least one event day', function () {
    registerAs(UserRole::Venue)
        ->set(managerDetails())
        ->call('next')
        ->set([
            'form.capacity' => '250',
            'form.location' => 'Cape Town',
            'form.region' => 'cape-town',
            'form.min_door_charge' => '0',
            'form.sound_engineer' => SoundEngineer::None->value,
            'form.has_own_equipment' => false,
        ])
        ->call('next')
        ->call('next')
        ->assertHasErrors(['form.event_days' => 'required']);

    expect(User::count())->toBe(0);
});

test('an artist can register with music and social links', function () {
    registerAs(UserRole::Artist)
        ->set(managerDetails())
        ->call('next')
        ->set([
            'form.artist_name' => 'The Loud Ones',
            'form.member_count' => '4',
            'form.member_names' => 'Ann, Ben, Cat, Dan',
            'form.genre' => 'Rock',
            'form.region' => 'johannesburg',
        ])
        ->call('next')
        ->assertHasNoErrors()
        ->set('form.music_links', [['platform' => LinkPlatform::Spotify->value, 'url' => 'https://open.spotify.com/artist/1']])
        ->set('form.social_links', [['platform' => LinkPlatform::Instagram->value, 'url' => 'https://instagram.com/loudones']])
        ->call('next')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $profile = User::sole()->artistProfile;

    expect($profile)
        ->artist_name->toBe('The Loud Ones')
        ->member_count->toBe(4)
        ->bio->toBeNull()
        ->and($profile->links->map(fn ($link) => [$link->kind, $link->platform, $link->url])->all())->toBe([
            [LinkKind::Music, LinkPlatform::Spotify, 'https://open.spotify.com/artist/1'],
            [LinkKind::Social, LinkPlatform::Instagram, 'https://instagram.com/loudones'],
        ]);
});

test('links must be real urls on a platform that fits their kind', function () {
    registerAs(UserRole::Artist)
        ->set(managerDetails())
        ->call('next')
        ->set(['form.artist_name' => 'X', 'form.member_count' => '1', 'form.member_names' => 'X', 'form.genre' => 'Pop', 'form.region' => 'johannesburg'])
        ->call('next')
        ->set('form.music_links', [['platform' => LinkPlatform::Instagram->value, 'url' => 'javascript:alert(1)']])
        ->call('next')
        ->assertHasErrors(['form.music_links.0.platform', 'form.music_links.0.url']);

    expect(User::count())->toBe(0);
});

test('an organiser can register', function () {
    registerAs(UserRole::Organiser)
        ->set([
            'form.org_name' => 'Gig Co',
            'form.contact_number' => '0211234567',
            'form.contact_email' => 'hello@gigco.test',
        ])
        ->set('form.social_links', [['platform' => LinkPlatform::Instagram->value, 'url' => 'https://instagram.com/gigco']])
        ->call('next')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();

    expect($user->role)->toBe(UserRole::Organiser)
        ->and($user->organiserProfile)->org_name->toBe('Gig Co')
        ->and($user->organiserProfile->links)->toHaveCount(1)
        ->and($user->venueProfile)->toBeNull()
        ->and($user->artistProfile)->toBeNull();
});

test('the final step re-validates every step for the role', function () {
    // The personal step already passed; blanking one of its fields must still block sign-up.
    registerAs(UserRole::Organiser)
        ->set(['form.org_name' => 'Gig Co', 'form.contact_number' => '0211234567', 'form.contact_email' => 'hello@gigco.test'])
        ->set('form.name', '')
        ->call('next')
        ->assertHasErrors(['form.name' => 'required']);

    expect(User::count())->toBe(0);
});
