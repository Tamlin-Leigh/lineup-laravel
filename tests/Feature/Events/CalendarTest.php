<?php

use App\Livewire\Events\Calendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
});

test('the calendar shows this month and the events in it', function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $user = User::factory()->artist()->create();
    Event::factory()->for($user, 'owner')->create(['title' => 'October Gig', 'starts_at' => '2026-10-20 20:00']);
    Event::factory()->for($user, 'owner')->create(['title' => 'November Gig', 'starts_at' => '2026-11-03 20:00']);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSee('October 2026')
        ->assertSee('October Gig')
        ->assertDontSee('November Gig');
});

test('the month grid starts on Sunday and is made of whole weeks', function () {
    Carbon::setTestNow('2026-10-08 09:00');

    $weeks = Livewire::actingAs(User::factory()->artist()->create())
        ->test(Calendar::class)
        ->get('weeks');

    // 1 October 2026 is a Thursday, so the first row has four blank days.
    expect($weeks)->toHaveCount(5)
        ->and($weeks[0][4]->format('Y-m-d'))->toBe('2026-10-01')
        ->and(array_slice($weeks[0], 0, 4))->each->toBeNull();
});

test('users can move between months', function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $user = User::factory()->artist()->create();
    Event::factory()->for($user, 'owner')->create(['title' => 'November Gig', 'starts_at' => '2026-11-03 20:00']);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->call('nextMonth')
        ->assertSet('month', '2026-11')
        ->assertSee('November Gig')
        ->call('previousMonth')
        ->call('previousMonth')
        ->assertSet('month', '2026-09')
        ->call('today')
        ->assertSee('October 2026');
});

test('cancelled events and other users\' events are left off', function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $user = User::factory()->artist()->create();
    Event::factory()->for($user, 'owner')->cancelled()->create(['title' => 'Called Off', 'starts_at' => '2026-10-20 20:00']);
    Event::factory()->create(['title' => 'Not Mine', 'starts_at' => '2026-10-21 20:00']);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertDontSee('Called Off')
        ->assertDontSee('Not Mine');
});

test("today's events are listed beside the calendar", function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $user = User::factory()->artist()->create();
    Event::factory()->for($user, 'owner')->create(['title' => 'Tonight', 'starts_at' => '2026-10-08 20:00']);

    $component = Livewire::actingAs($user)->test(Calendar::class);

    expect($component->get('todaysEvents')->pluck('title')->all())->toBe(['Tonight']);
});

test('a malformed month in the link falls back to the current month', function () {
    Carbon::setTestNow('2026-10-08 09:00');

    Livewire::actingAs(User::factory()->artist()->create())
        ->withQueryParams(['month' => '2026-13'])
        ->test(Calendar::class)
        ->assertSee('October 2026');
});
