<?php

use App\Models\Event;
use App\Models\User;

test('venues, bands and organisers can create events but admins cannot', function (string $state, bool $allowed) {
    expect(User::factory()->{$state}()->create()->can('create', Event::class))->toBe($allowed);
})->with([
    'venue' => ['venue', true],
    'band' => ['band', true],
    'organiser' => ['organiser', true],
    'admin' => ['admin', false],
]);

test('owners can update and delete their events', function () {
    $event = Event::factory()->create();

    expect($event->owner->can('update', $event))->toBeTrue()
        ->and($event->owner->can('delete', $event))->toBeTrue();
});

test("other users cannot update or delete someone else's event", function () {
    $event = Event::factory()->create();
    $other = User::factory()->band()->create();

    expect($other->can('update', $event))->toBeFalse()
        ->and($other->can('delete', $event))->toBeFalse();
});

test('admins can manage any event', function () {
    expect(User::factory()->admin()->create()->can('update', Event::factory()->create()))->toBeTrue();
});

test('published events are visible to everyone, drafts only to their owner', function () {
    $other = User::factory()->venue()->create();
    $published = Event::factory()->create();
    $draft = Event::factory()->draft()->create();

    expect($other->can('view', $published))->toBeTrue()
        ->and($other->can('view', $draft))->toBeFalse()
        ->and($draft->owner->can('view', $draft))->toBeTrue();
});
