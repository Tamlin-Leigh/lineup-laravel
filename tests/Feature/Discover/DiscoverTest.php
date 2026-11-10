<?php

use App\Livewire\Discover;
use App\Models\Availability;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 8 October 2026, so "this weekend" is Fri 9 - Sun 11 October (the brief's example).
    Carbon::setTestNow('2026-10-08 09:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function freeOn(User $user, string ...$dates): User
{
    foreach ($dates as $date) {
        Availability::factory()->for($user)->create(['date' => $date]);
    }

    return $user;
}

/**
 * @return list<int>
 */
function foundIds(Testable $component): array
{
    return $component->get('results')->pluck('id')->all();
}

test('discover is for venues, artists and organisers', function (string $state, int $status) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('discover'))
        ->assertStatus($status);
})->with([
    'venue' => ['venue', 200],
    'artist' => ['artist', 200],
    'organiser' => ['organiser', 200],
    'admin' => ['admin', 403],
]);

test('by default it shows who is free this weekend', function () {
    $venue = freeOn(User::factory()->venue()->create(), '2026-10-10');
    $artist = freeOn(User::factory()->artist()->create(), '2026-10-09', '2026-10-11');
    freeOn(User::factory()->artist()->create(), '2026-10-17');

    $component = Livewire::actingAs(User::factory()->organiser()->create())->test(Discover::class);

    expect(foundIds($component))->toBe([$artist->id, $venue->id]);
    $component->assertSee('Fri 9 Oct')->assertSee('Sat 10 Oct');
});

test('another weekend can be picked', function () {
    freeOn(User::factory()->venue()->create(), '2026-10-10');
    $later = freeOn(User::factory()->artist()->create(), '2026-10-17');

    $component = Livewire::actingAs(User::factory()->organiser()->create())
        ->test(Discover::class)
        ->set('weekend', '2026-10-16');

    expect(foundIds($component))->toBe([$later->id]);
});

test('results can be narrowed to one role', function () {
    freeOn(User::factory()->venue()->create(), '2026-10-10');
    $artist = freeOn(User::factory()->artist()->create(), '2026-10-10');
    freeOn(User::factory()->organiser()->create(), '2026-10-10');

    $component = Livewire::actingAs(User::factory()->venue()->create())
        ->test(Discover::class)
        ->set('role', 'artist');

    expect(foundIds($component))->toBe([$artist->id]);
});

test('a single day can be searched', function () {
    freeOn(User::factory()->venue()->create(), '2026-10-10');
    $tuesday = freeOn(User::factory()->artist()->create(), '2026-10-13');

    $component = Livewire::actingAs(User::factory()->organiser()->create())
        ->test(Discover::class)
        ->set(['when' => 'day', 'day' => '2026-10-13']);

    expect(foundIds($component))->toBe([$tuesday->id]);
});

test('a date range finds anyone free on any day in it, most matching days first', function () {
    $once = freeOn(User::factory()->venue()->create(['name' => 'Alpha Venue']), '2026-10-20');
    $twice = freeOn(User::factory()->venue()->create(['name' => 'Zulu Venue']), '2026-10-21', '2026-10-22');
    freeOn(User::factory()->venue()->create(), '2026-11-30');

    $component = Livewire::actingAs(User::factory()->artist()->create())
        ->test(Discover::class)
        ->set(['when' => 'range', 'from' => '2026-10-19', 'to' => '2026-10-25']);

    expect(foundIds($component))->toBe([$twice->id, $once->id]);
});

test('you, admins and unverified accounts are never listed', function () {
    $me = freeOn(User::factory()->artist()->create(), '2026-10-10');
    freeOn(User::factory()->admin()->create(), '2026-10-10');
    freeOn(User::factory()->venue()->unverified()->create(), '2026-10-10');

    $component = Livewire::actingAs($me)->test(Discover::class);

    expect(foundIds($component))->toBe([]);
    $component->assertSee('Nobody matching your filters');
});

test('search matches names, genre, organisation and venue location', function (string $term, string $expected) {
    freeOn(User::factory()->venue()->create(['name' => 'The Loft']), '2026-10-10')->venueProfile->update(['location' => 'Long Street, Cape Town']);
    freeOn(User::factory()->artist()->create(), '2026-10-10')->artistProfile->update(['artist_name' => 'Night Owls', 'genre' => 'Jazz']);
    freeOn(User::factory()->organiser()->create(), '2026-10-10')->organiserProfile->update(['org_name' => 'Gig Co']);

    $component = Livewire::actingAs(User::factory()->artist()->create())
        ->test(Discover::class)
        ->set('search', $term);

    expect($component->get('results')->getCollection()->map->displayName()->all())->toBe([$expected]);
})->with([
    'venue name' => ['loft', 'The Loft'],
    'venue location' => ['cape town', 'The Loft'],
    'artist name' => ['owls', 'Night Owls'],
    'genre' => ['jazz', 'Night Owls'],
    'organisation' => ['gig co', 'Gig Co'],
]);

test('filters are kept in the address so a search can be shared', function () {
    Livewire::actingAs(User::factory()->artist()->create())
        ->withQueryParams(['role' => 'venue', 'when' => 'day', 'day' => '2026-10-13'])
        ->test(Discover::class)
        ->assertSet('role', 'venue')
        ->assertSet('when', 'day')
        ->assertSet('day', '2026-10-13');
});

test('past or malformed dates fall back to sensible ones', function () {
    $component = Livewire::actingAs(User::factory()->artist()->create())
        ->withQueryParams(['when' => 'range', 'from' => '2026-01-01', 'to' => 'not-a-date'])
        ->test(Discover::class);

    [$from, $to] = $component->instance()->range();

    expect($from->format('Y-m-d'))->toBe('2026-10-08')
        ->and($to->format('Y-m-d'))->toBe('2026-10-21');
});

test('on a Saturday, this weekend is the rest of the current one', function () {
    Carbon::setTestNow('2026-10-10 09:00');

    [$from, $to] = Livewire::actingAs(User::factory()->artist()->create())->test(Discover::class)->instance()->range();

    expect([$from->format('D j'), $to->format('D j')])->toBe(['Sat 10', 'Sun 11']);
});
