<?php

use App\Enums\Region;
use App\Livewire\Discover;
use App\Livewire\Events\ManageEvent;
use App\Livewire\Profile\EditProfile;
use App\Models\Availability;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 09:00');
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

function artistIn(?Region $region, string $freeOn = '2026-10-24'): User
{
    $artist = User::factory()->artist()->create();
    $artist->artistProfile->update(['region' => $region]);
    Availability::factory()->for($artist)->create(['date' => $freeOn]);

    return $artist;
}

function venueIn(?Region $region): User
{
    $venue = User::factory()->venue()->create();
    $venue->venueProfile->update(['region' => $region]);

    return $venue;
}

/**
 * @return list<int>
 */
function listedArtists(Testable $component): array
{
    return $component->instance()->freeArtists->pluck('id')->sort()->values()->all();
}

function newEventFor(User $host): Testable
{
    return Livewire::actingAs($host)->test(ManageEvent::class)->set('form.date', '2026-10-24');
}

test('an artist has to choose a valid region and it is saved on their profile', function () {
    $artist = User::factory()->artist()->create();
    $artist->artistProfile->update(['region' => null]);

    $component = Livewire::actingAs($artist->fresh())->test(EditProfile::class)
        ->call('edit')
        ->call('save')
        ->assertHasErrors(['form.region' => 'required'])
        ->assertSet('tab', 'artist')
        ->set('form.region', 'atlantis')
        ->call('save')
        ->assertHasErrors('form.region')
        ->set('form.region', Region::Durban->value)
        ->call('save')
        ->assertHasNoErrors();

    $component->assertSee('Durban');
    expect($artist->artistProfile->fresh()->region)->toBe(Region::Durban);
});

test('a venue saves its region and an optional stage size', function () {
    $venue = venueIn(null);

    Livewire::actingAs($venue->fresh())->test(EditProfile::class)
        ->call('edit')
        ->call('save')
        ->assertHasErrors(['form.region' => 'required'])
        ->assertSet('tab', 'venue')
        ->set('form.region', Region::CapeTown->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($venue->venueProfile->fresh())
        ->region->toBe(Region::CapeTown)
        ->stage_width->toBeNull()
        ->stageSize()->toBeNull();

    Livewire::actingAs($venue->fresh())->test(EditProfile::class)
        ->call('edit')
        ->set(['form.stage_width' => '6', 'form.stage_depth' => '4.5'])
        ->call('save')
        ->assertHasNoErrors()
        ->call('edit')
        ->assertSet('form.stage_width', '6')
        ->assertSet('form.stage_depth', '4.5');

    expect($venue->venueProfile->fresh()->stageSize())->toBe('6 m × 4.5 m');

    Livewire::actingAs($venue->fresh())->test(EditProfile::class)
        ->call('edit')
        ->set(['form.stage_width' => '', 'form.stage_depth' => ''])
        ->call('save')
        ->assertHasNoErrors();

    expect($venue->venueProfile->fresh()->stageSize())->toBeNull();
});

test('a stage size needs both measurements, in sensible units', function (array $input, string $error) {
    Livewire::actingAs(User::factory()->venue()->create())->test(EditProfile::class)
        ->call('edit')
        ->set($input)
        ->call('save')
        ->assertHasErrors($error)
        ->assertSet('tab', 'venue');
})->with([
    'width only' => [['form.stage_width' => '6'], 'form.stage_depth'],
    'depth only' => [['form.stage_depth' => '4'], 'form.stage_width'],
    'too small' => [['form.stage_width' => '0.5', 'form.stage_depth' => '4'], 'form.stage_width'],
    'too big' => [['form.stage_width' => '100', 'form.stage_depth' => '4'], 'form.stage_width'],
    'too precise' => [['form.stage_width' => '6.25', 'form.stage_depth' => '4'], 'form.stage_width'],
]);

test('public profiles show the region and stage size', function () {
    $venue = venueIn(Region::Pretoria);
    $venue->venueProfile->update(['stage_width' => 8, 'stage_depth' => 5]);

    $this->actingAs(User::factory()->organiser()->create())
        ->get(route('profiles.show', $venue))
        ->assertOk()
        ->assertSee('Pretoria')
        ->assertSee('8 m × 5 m');

    $this->get(route('profiles.show', artistIn(Region::Limpopo)))
        ->assertOk()
        ->assertSee('Limpopo');
});

test('discover can be filtered by region', function () {
    $joburg = artistIn(Region::Johannesburg);
    $durban = artistIn(Region::Durban);
    $notSet = artistIn(null);
    $venue = venueIn(Region::Durban);
    Availability::factory()->for($venue)->create(['date' => '2026-10-24']);
    $organiser = User::factory()->organiser()->create();
    Availability::factory()->for($organiser)->create(['date' => '2026-10-24']);

    $found = fn (Testable $component) => $component->instance()->results->pluck('id')->sort()->values()->all();

    $component = Livewire::actingAs(User::factory()->venue()->create())
        ->test(Discover::class)
        ->set(['when' => 'day', 'day' => '2026-10-24']);

    expect($found($component))->toEqual(collect([$joburg, $durban, $notSet, $venue, $organiser])->pluck('id')->sort()->values()->all());

    expect($found($component->set('region', Region::Durban->value)))->toEqual(collect([$durban, $venue])->pluck('id')->sort()->values()->all())
        ->and($found($component->set('region', 'atlantis')))->toHaveCount(5);
});

test('a venue books from its own region by default and can switch to any other', function () {
    $venue = venueIn(Region::CapeTown);
    $local = artistIn(Region::CapeTown);
    $joburg = artistIn(Region::Johannesburg);
    $notSet = artistIn(null);

    $component = newEventFor($venue);

    expect(listedArtists($component))->toBe([$local->id]);

    $component->set('useVenueRegion', false);
    expect(listedArtists($component))->toBe([$local->id, $joburg->id, $notSet->id]);

    $component->set('form.region', Region::Johannesburg->value);
    expect(listedArtists($component))->toBe([$joburg->id]);

    $component->set('useVenueRegion', true);
    expect(listedArtists($component))->toBe([$local->id]);
});

test('the event saves the region it books from', function (bool $useVenue, string $picked, ?Region $saved) {
    $venue = venueIn(Region::CapeTown);

    Livewire::actingAs($venue)->test(ManageEvent::class)
        ->set([
            'form.title' => 'Big Night',
            'form.location' => 'The Park',
            'form.date' => '2026-10-24',
            'form.artist_slots' => '1',
            'useVenueRegion' => $useVenue,
            'form.region' => $picked,
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($venue->events()->sole()->region)->toBe($saved);
})->with([
    'the venue default' => [true, '', Region::CapeTown],
    'a default ignores a stale pick' => [true, 'durban', Region::CapeTown],
    'another region' => [false, 'johannesburg', Region::Johannesburg],
    'every region' => [false, '', null],
]);

test('switching region unticks artists who are no longer listed', function () {
    $venue = venueIn(Region::CapeTown);
    $local = artistIn(Region::CapeTown);

    newEventFor($venue)
        ->set('artistIds', [(string) $local->id])
        ->set('useVenueRegion', false)
        ->assertSet('artistIds', [(string) $local->id])
        ->set('form.region', Region::Johannesburg->value)
        ->assertSet('artistIds', []);
});

test('searching does not untick artists when the region changes', function () {
    $venue = venueIn(Region::CapeTown);
    $picked = artistIn(Region::CapeTown);
    $other = artistIn(Region::CapeTown);
    $other->update(['name' => 'Searchable']);

    newEventFor($venue)
        ->set('artistIds', [(string) $picked->id])
        ->set('artistSearch', 'Searchable')
        ->set('useVenueRegion', false)
        ->assertSet('artistIds', [(string) $picked->id]);
});

test('the default follows the venue picked by an organiser', function () {
    $venue = venueIn(Region::Durban);
    Availability::factory()->for($venue)->create(['date' => '2026-10-24']);
    $inDurban = artistIn(Region::Durban);
    $elsewhere = artistIn(Region::Pretoria);

    $component = newEventFor(User::factory()->organiser()->create());

    expect($component->instance()->venueRegion())->toBeNull()
        ->and(listedArtists($component))->toBe([$inDurban->id, $elsewhere->id]);

    $component->set('venueId', (string) $venue->id);

    expect($component->instance()->venueRegion())->toBe(Region::Durban)
        ->and(listedArtists($component))->toBe([$inDurban->id]);
});

test('without a venue region the host just picks one', function () {
    $venue = venueIn(null);
    $durban = artistIn(Region::Durban);
    $pretoria = artistIn(Region::Pretoria);

    $component = newEventFor($venue);

    expect($component->html())->not->toContain('data-test="use-venue-region"')
        ->and(listedArtists($component))->toBe([$durban->id, $pretoria->id]);

    $component->set('form.region', Region::Pretoria->value);

    expect(listedArtists($component))->toBe([$pretoria->id]);
});

test('editing an event keeps the region it was saved with', function (?Region $saved, bool $followsVenue) {
    $venue = venueIn(Region::CapeTown);
    $event = Event::factory()->for($venue, 'owner')->draft()->create([
        'venue_profile_id' => $venue->venueProfile->id,
        'region' => $saved,
        'starts_at' => '2026-10-24 20:00',
        'artist_slots' => 1,
    ]);

    Livewire::actingAs($venue)->test(ManageEvent::class, ['event' => $event])
        ->assertSet('useVenueRegion', $followsVenue)
        ->set('form.title', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh())
        ->title->toBe('Renamed')
        ->region->toBe($saved);
})->with([
    'the venue region' => [Region::CapeTown, true],
    'another region' => [Region::Durban, false],
    'an older event with no region' => [null, false],
]);

test('the event form shows the toggle, and the region list once it is off', function () {
    $venue = venueIn(Region::CapeTown);
    $event = Event::factory()->for($venue, 'owner')->draft()->create([
        'venue_profile_id' => $venue->venueProfile->id,
        'region' => Region::CapeTown,
        'starts_at' => '2026-10-24 20:00',
    ]);

    Livewire::actingAs($venue)->test(ManageEvent::class, ['event' => $event])
        ->assertSeeHtml('data-test="event-region"')
        ->assertSee('Use the venue&#039;s region (Cape Town)', false)
        ->assertDontSee('Book artists from')
        ->set('useVenueRegion', false)
        ->assertSee('Book artists from')
        ->assertSee('All regions')
        ->assertSee('Mpumalanga');
});

test('the event page shows its region', function () {
    $event = Event::factory()->create(['region' => Region::FreeState]);

    $this->actingAs(User::factory()->organiser()->create())
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Free State');
});
