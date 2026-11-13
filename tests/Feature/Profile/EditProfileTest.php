<?php

use App\Enums\EventDay;
use App\Enums\LinkKind;
use App\Enums\LinkPlatform;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use App\Livewire\Profile\EditProfile;
use App\Models\Link;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('guests are sent to log in', function () {
    $this->get(route('profile.show'))->assertRedirect(route('login'));
});

test('each role sees its own profile', function (string $state, string $expected) {
    $user = User::factory()->{$state}()->create();
    $profile = $user->venueProfile ?? $user->artistProfile ?? $user->organiserProfile;

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSeeLivewire(EditProfile::class)
        ->assertSee($profile->{$expected});
})->with([
    'venue' => ['venue', 'location'],
    'artist' => ['artist', 'artist_name'],
    'organiser' => ['organiser', 'org_name'],
]);

test('admins are sent to their account settings', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditProfile::class)
        ->assertRedirect(route('profile.edit'));
});

test('an artist can edit its profile', function () {
    $user = User::factory()->artist()->create();

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->assertSet('editing', true)
        ->assertSet('form.artist_name', $user->artistProfile->artist_name)
        ->set([
            'form.name' => 'New Name',
            'form.phone' => '+27 82 000 1111',
            'form.artist_name' => 'The New Sound',
            'form.member_count' => '3',
            'form.bio' => '',
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    $user->refresh();

    expect($user)
        ->name->toBe('New Name')
        ->phone->toBe('+27820001111')
        ->and($user->artistProfile)
        ->artist_name->toBe('The New Sound')
        ->member_count->toBe(3)
        ->bio->toBeNull();
});

test('a venue can edit its profile', function () {
    $user = User::factory()->venue()->create();
    $user->venueProfile->update(['sound_engineer' => SoundEngineer::InHouse, 'sound_engineer_name' => 'Alex']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->set([
            'form.capacity' => '400',
            'form.sound_engineer' => SoundEngineer::None->value,
            'form.has_own_equipment' => false,
            'form.event_days' => [EventDay::Weekdays->value, EventDay::Weekend->value],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->venueProfile->fresh())
        ->capacity->toBe(400)
        ->sound_engineer->toBe(SoundEngineer::None)
        ->sound_engineer_name->toBeNull()
        ->equipment_details->toBeNull()
        ->and($user->venueProfile->fresh()->event_days->all())->toBe([EventDay::Weekdays, EventDay::Weekend]);
});

test('an organiser can replace their links', function () {
    $user = User::factory()->organiser()->create();
    $old = Link::factory()->for($user->organiserProfile, 'linkable')->create([
        'kind' => LinkKind::Social,
        'platform' => LinkPlatform::Facebook,
        'url' => 'https://facebook.com/old',
    ]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->assertCount('form.social_links', 1)
        ->set('form.social_links', [['platform' => LinkPlatform::Instagram->value, 'url' => 'https://instagram.com/new']])
        ->call('save')
        ->assertHasNoErrors();

    expect(Link::find($old->id))->toBeNull()
        ->and($user->organiserProfile->links()->sole())
        ->platform->toBe(LinkPlatform::Instagram)
        ->url->toBe('https://instagram.com/new');
});

test('a venue can add social links', function () {
    $user = User::factory()->venue()->create();

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->assertSee('Links')
        ->call('edit')
        ->set('form.social_links', [['platform' => LinkPlatform::Facebook->value, 'url' => 'https://facebook.com/thevenue']])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->venueProfile->links()->sole())
        ->kind->toBe(LinkKind::Social)
        ->platform->toBe(LinkPlatform::Facebook)
        ->url->toBe('https://facebook.com/thevenue');

    Livewire::actingAs($user->fresh())
        ->test(EditProfile::class)
        ->assertSet('form.social_links', [['platform' => LinkPlatform::Facebook->value, 'url' => 'https://facebook.com/thevenue']]);
});

test('a venue cannot add music links', function () {
    $user = User::factory()->venue()->create();

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->set('form.social_links', [['platform' => LinkPlatform::Spotify->value, 'url' => 'https://open.spotify.com/x']])
        ->call('save')
        ->assertHasErrors('form.social_links.0.platform')
        ->assertSet('tab', 'links');

    expect($user->venueProfile->links()->count())->toBe(0);
});

test('venue links show on its public profile', function () {
    $venue = User::factory()->venue()->create();
    Link::factory()->for($venue->venueProfile, 'linkable')->create([
        'kind' => LinkKind::Social,
        'platform' => LinkPlatform::Instagram,
        'url' => 'https://instagram.com/thevenue',
    ]);

    $this->actingAs(User::factory()->artist()->create())
        ->get(route('profiles.show', $venue))
        ->assertOk()
        ->assertSee('https://instagram.com/thevenue');
});

test('saving one kind of link leaves the other kind alone', function () {
    $user = User::factory()->artist()->create();
    $artist = $user->artistProfile;
    Link::factory()->for($artist, 'linkable')->create(['kind' => LinkKind::Social, 'platform' => LinkPlatform::TikTok]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->set('form.music_links', [['platform' => LinkPlatform::Bandcamp->value, 'url' => 'https://bandcamp.com/x']])
        ->call('save')
        ->assertHasNoErrors();

    expect($artist->links()->pluck('kind')->all())->toEqualCanonicalizing([LinkKind::Social, LinkKind::Music]);
});

test('a failed save shows the tab with the first error', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->test(EditProfile::class)
        ->call('edit')
        ->set('form.manager_email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['form.manager_email' => 'email'])
        ->assertSet('tab', 'manager')
        ->assertSet('editing', true);
});

test('cancelling discards unsaved changes', function () {
    $user = User::factory()->artist()->create(['name' => 'Original']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->set('form.name', 'Changed')
        ->call('cancel')
        ->assertSet('editing', false)
        ->assertSet('form.name', 'Original');

    expect($user->fresh()->name)->toBe('Original');
});

test('saving never changes email or role', function () {
    $user = User::factory()->artist()->create(['email' => 'artist@example.com']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->email->toBe('artist@example.com')
        ->role->toBe(UserRole::Artist);
});

test('the profile being edited cannot be swapped from the browser', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->test(EditProfile::class)
        ->set('user', User::factory()->artist()->create());
})->throws(CannotUpdateLockedPropertyException::class);

test('a profile missing its role record is created on first save', function () {
    $user = User::factory()->create(['role' => UserRole::Organiser]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->set([
            'form.org_name' => 'Late Starters',
            'form.contact_number' => '0211234567',
            'form.contact_email' => 'hi@late.test',
            'form.region' => 'durban',
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->organiserProfile()->sole()->org_name)->toBe('Late Starters');
});

test('every role gets the same tabbed profile, with the tabs for its profile', function (string $state, array $tabs) {
    $component = Livewire::actingAs(User::factory()->{$state}()->create())->test(EditProfile::class);

    expect(array_keys($component->instance()->tabs()))->toBe($tabs);

    foreach ($tabs as $tab) {
        $component->assertSeeHtml('data-test="profile-tab-'.$tab.'"');
    }
})->with([
    'venue' => ['venue', ['account', 'venue', 'manager', 'event-days', 'links']],
    'artist' => ['artist', ['account', 'artist', 'members', 'manager', 'links']],
    'organiser' => ['organiser', ['account', 'organiser', 'links']],
]);

test('a failed save jumps to the tab with the error for every role', function (string $state, string $field, string|array $value, string $tab) {
    Livewire::actingAs(User::factory()->{$state}()->create())
        ->test(EditProfile::class)
        ->call('edit')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field])
        ->assertSet('tab', $tab);
})->with([
    'venue capacity' => ['venue', 'form.capacity', '0', 'venue'],
    'venue manager email' => ['venue', 'form.manager_email', 'ryan@gmail', 'manager'],
    'venue event days' => ['venue', 'form.event_days', [], 'event-days'],
    'organiser contact email' => ['organiser', 'form.contact_email', 'not-an-email', 'organiser'],
    'organiser name' => ['organiser', 'form.name', '', 'account'],
]);

test('a tab the role does not have falls back to Account', function () {
    $component = Livewire::actingAs(User::factory()->organiser()->create())
        ->test(EditProfile::class)
        ->set('tab', 'members');

    expect($component->instance()->activeTab())->toBe('account');
});

test('links show the logo of their platform', function () {
    $user = User::factory()->artist()->create();
    Link::factory()->for($user->artistProfile, 'linkable')->create(['kind' => LinkKind::Music, 'platform' => LinkPlatform::Spotify]);
    Link::factory()->for($user->artistProfile, 'linkable')->create(['kind' => LinkKind::Social, 'platform' => LinkPlatform::Website]);

    $html = Livewire::actingAs($user)->test(EditProfile::class)->html();

    expect($html)
        ->toContain('color: #1DB954')
        ->toContain('<title>Spotify</title>')
        ->toContain('color: #184e6c');
});

test('every platform has an icon and a colour', function (LinkPlatform $platform) {
    expect($platform->color())->toMatch('/^#[0-9A-F]{6}$/i');

    if ($icon = $platform->brandIcon()) {
        expect(svg($icon)->contents())->toContain('<path');
    } else {
        expect($platform->fallbackIcon())->toBeIn(['globe-alt', 'link']);
    }
})->with(LinkPlatform::cases());

test('the link editor draws its rows in the browser, with an icon per platform', function () {
    $html = Livewire::actingAs(User::factory()->artist()->create())
        ->test(EditProfile::class)
        ->call('edit')
        ->html();

    expect($html)
        ->toContain('data-test="link-repeater-music_links"')
        ->toContain('x-model="link.platform"')
        ->not->toContain('wire:click="addLink')
        ->toContain('<title>Spotify</title>')
        ->toContain('<title>Bandcamp</title>');
});
