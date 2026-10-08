<?php

use App\Livewire\Profile\AvatarUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a user can upload a profile photo', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)
        ->test(AvatarUpload::class, ['user' => $user])
        ->set('photo', UploadedFile::fake()->image('me.jpg', 400, 400))
        ->assertHasNoErrors()
        ->assertSet('photo', null);

    $path = $user->fresh()->profile_photo_path;

    expect($path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($path);
});

test('uploading a new photo deletes the old one', function () {
    $user = User::factory()->band()->create();
    $component = Livewire::actingAs($user)->test(AvatarUpload::class, ['user' => $user]);

    $component->set('photo', UploadedFile::fake()->image('first.png'));
    $first = $user->fresh()->profile_photo_path;

    $component->set('photo', UploadedFile::fake()->image('second.png'));

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()->profile_photo_path);
});

test('only images under 5 MB are accepted', function (UploadedFile $file) {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)
        ->test(AvatarUpload::class, ['user' => $user])
        ->set('photo', $file)
        ->assertHasErrors('photo');

    expect($user->fresh()->profile_photo_path)->toBeNull();
})->with([
    'not an image' => fn () => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('huge.jpg')->size(6000),
]);

test('a user can remove their photo', function () {
    $user = User::factory()->band()->create();
    $component = Livewire::actingAs($user)->test(AvatarUpload::class, ['user' => $user]);

    $component->set('photo', UploadedFile::fake()->image('me.jpg'));
    $path = $user->fresh()->profile_photo_path;

    $component->call('removePhoto');

    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test("nobody else can change a user's photo", function () {
    $owner = User::factory()->band()->create();

    Livewire::actingAs(User::factory()->band()->create())
        ->test(AvatarUpload::class, ['user' => $owner])
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->assertForbidden();

    expect($owner->fresh()->profile_photo_path)->toBeNull();
});

test('deleting an account deletes its photo', function () {
    $user = User::factory()->band()->create();
    Livewire::actingAs($user)
        ->test(AvatarUpload::class, ['user' => $user])
        ->set('photo', UploadedFile::fake()->image('me.jpg'));
    $user = $user->fresh();
    $path = $user->profile_photo_path;

    $user->delete();

    Storage::disk('public')->assertMissing($path);
});
