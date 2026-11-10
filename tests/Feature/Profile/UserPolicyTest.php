<?php

use App\Models\User;

test('users can update their own profile', function () {
    $user = User::factory()->artist()->create();

    expect($user->can('update', $user))->toBeTrue();
});

test("users cannot update someone else's profile", function () {
    $user = User::factory()->artist()->create();

    expect($user->can('update', User::factory()->venue()->create()))->toBeFalse();
});

test('admins can update any profile', function () {
    expect(User::factory()->admin()->create()->can('update', User::factory()->venue()->create()))->toBeTrue();
});
