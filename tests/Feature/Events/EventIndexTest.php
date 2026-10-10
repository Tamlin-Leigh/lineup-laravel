<?php

use App\Livewire\Events\Index;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

test('the events page is for venues, bands and organisers', function (string $state, int $status) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('events.index'))
        ->assertStatus($status);
})->with([
    'venue' => ['venue', 200],
    'band' => ['band', 200],
    'organiser' => ['organiser', 200],
    'admin' => ['admin', 403],
]);

test('users only see their own events', function () {
    $user = User::factory()->band()->create();
    Event::factory()->for($user, 'owner')->create(['title' => 'Mine']);
    Event::factory()->create(['title' => 'Someone Else']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Mine')
        ->assertDontSee('Someone Else');
});

test('tabs show counts and switch between event states', function () {
    $user = User::factory()->band()->create();
    Event::factory()->for($user, 'owner')->count(2)->create();
    Event::factory()->for($user, 'owner')->draft()->create(['title' => 'Draft Gig']);
    Event::factory()->for($user, 'owner')->past()->create(['title' => 'Old Gig']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSet('counts', ['active' => 2, 'draft' => 1, 'past' => 1, 'cancelled' => 0])
        ->assertDontSee('Old Gig')
        ->set('tab', 'past')
        ->assertSee('Old Gig')
        ->assertDontSee('Draft Gig');
});

test('search matches title or location', function () {
    $user = User::factory()->band()->create();
    Event::factory()->for($user, 'owner')->create(['title' => 'Jazz Night', 'location' => 'Durban']);
    Event::factory()->for($user, 'owner')->create(['title' => 'Rock Night', 'location' => 'Cape Town']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', 'cape')
        ->assertSee('Rock Night')
        ->assertDontSee('Jazz Night');
});

test('search treats % and _ literally', function () {
    $user = User::factory()->band()->create();
    Event::factory()->for($user, 'owner')->create(['title' => 'Plain Show']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('search', '%')
        ->assertDontSee('Plain Show');
});

test('each event shows how many of its band slots are filled', function () {
    $user = User::factory()->venue()->create();
    $event = Event::factory()->for($user, 'owner')->create(['band_slots' => 3]);
    BookingRequest::factory()->for($event)->accepted()->create();
    BookingRequest::factory()->for($event)->declined()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('1 of 3 bands');
});

test('results are paged six at a time', function () {
    $user = User::factory()->band()->create();
    Event::factory()->for($user, 'owner')->count(8)->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Showing 6 out of 8')
        ->call('nextPage')
        ->assertSee('Showing 2 out of 8');
});

test('users can delete their own events', function () {
    $user = User::factory()->band()->create();
    $event = Event::factory()->for($user, 'owner')->create();

    Livewire::actingAs($user)->test(Index::class)->call('delete', $event->id);

    expect(Event::find($event->id))->toBeNull();
});

test("users cannot delete someone else's event", function () {
    $event = Event::factory()->create();

    Livewire::actingAs(User::factory()->band()->create())
        ->test(Index::class)
        ->call('delete', $event->id)
        ->assertNotFound();

    expect($event->fresh())->not->toBeNull();
});
