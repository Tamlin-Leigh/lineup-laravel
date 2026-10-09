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
    $profile = $user->venueProfile ?? $user->bandProfile ?? $user->organiserProfile;

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSeeLivewire(EditProfile::class)
        ->assertSee($profile->{$expected});
})->with([
    'venue' => ['venue', 'location'],
    'band' => ['band', 'band_name'],
    'organiser' => ['organiser', 'org_name'],
]);

test('admins are sent to their account settings', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(EditProfile::class)
        ->assertRedirect(route('profile.edit'));
});

test('a band can edit its profile', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->assertSet('editing', true)
        ->assertSet('form.band_name', $user->bandProfile->band_name)
        ->set([
            'form.name' => 'New Name',
            'form.phone' => '+27 82 000 1111',
            'form.band_name' => 'The New Sound',
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
        ->and($user->bandProfile)
        ->band_name->toBe('The New Sound')
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
        ->call('removeLink', 'social_links', 0)
        ->call('addLink', 'social_links')
        ->set('form.social_links.0.url', 'https://instagram.com/new')
        ->call('save')
        ->assertHasNoErrors();

    expect(Link::find($old->id))->toBeNull()
        ->and($user->organiserProfile->links()->sole())
        ->platform->toBe(LinkPlatform::Instagram)
        ->url->toBe('https://instagram.com/new');
});

test('saving one kind of link leaves the other kind alone', function () {
    $user = User::factory()->band()->create();
    $band = $user->bandProfile;
    Link::factory()->for($band, 'linkable')->create(['kind' => LinkKind::Social, 'platform' => LinkPlatform::TikTok]);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->call('addLink', 'music_links')
        ->set('form.music_links.0.url', 'https://bandcamp.com/x')
        ->call('save')
        ->assertHasNoErrors();

    expect($band->links()->pluck('kind')->all())->toEqualCanonicalizing([LinkKind::Social, LinkKind::Music]);
});

test('a failed save shows the tab with the first error', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->test(EditProfile::class)
        ->call('edit')
        ->set('form.manager_email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['form.manager_email' => 'email'])
        ->assertSet('tab', 'manager')
        ->assertSet('editing', true);
});

test('cancelling discards unsaved changes', function () {
    $user = User::factory()->band()->create(['name' => 'Original']);

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
    $user = User::factory()->band()->create(['email' => 'band@example.com']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->call('edit')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->email->toBe('band@example.com')
        ->role->toBe(UserRole::Band);
});

test('the profile being edited cannot be swapped from the browser', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->test(EditProfile::class)
        ->set('user', User::factory()->band()->create());
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
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($user->organiserProfile()->sole()->org_name)->toBe('Late Starters');
});
