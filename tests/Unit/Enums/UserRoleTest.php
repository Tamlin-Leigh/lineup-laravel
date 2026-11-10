<?php

use App\Enums\LinkKind;
use App\Enums\LinkPlatform;
use App\Enums\UserRole;

test('admin can never be chosen at sign-up', function () {
    expect(UserRole::registrable())
        ->not->toContain(UserRole::Admin)
        ->toHaveCount(3);
});

test('options map backing values to labels', function () {
    expect(UserRole::options())->toBe([
        'venue' => 'Venue',
        'artist' => 'Artist',
        'organiser' => 'Organiser',
        'admin' => 'Admin',
    ]);
});

test('music and social links offer separate platforms', function () {
    expect(LinkKind::Music->platforms())->toContain(LinkPlatform::Spotify)->not->toContain(LinkPlatform::Instagram)
        ->and(LinkKind::Social->platforms())->toContain(LinkPlatform::Instagram)->not->toContain(LinkPlatform::Spotify);
});
