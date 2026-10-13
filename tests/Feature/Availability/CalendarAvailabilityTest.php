<?php

use App\Livewire\Events\Calendar;
use App\Models\Availability;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 8 October 2026: the brief's weekends are Fri 9 / Sat 10 and Fri 16 / Sat 17.
    Carbon::setTestNow('2026-10-08 09:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('clicking days in marking mode toggles them', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('markingAvailability', true)
        ->call('toggleDay', '2026-10-09')
        ->call('toggleDay', '2026-10-10')
        ->assertSet('freeDays', ['2026-10-09' => true, '2026-10-10' => true])
        ->call('toggleDay', '2026-10-10')
        ->assertSet('freeDays', ['2026-10-09' => true]);
});

test('days flip in the browser first, and the re-render carries what was saved', function () {
    $user = User::factory()->band()->create();

    $component = Livewire::actingAs($user)
        ->test(Calendar::class)
        ->set('markingAvailability', true)
        ->assertSeeHtml("x-on:click=\"flip(\$el, '2026-10-09')\"")
        ->assertDontSeeHtml('wire:click="toggleDay');

    $day = fn () => str($component->html())->match('/<div\s+data-free="(true|false)"[^>]*data-test="calendar-day-2026-10-09"/s')->toString();

    expect($day())->toBe('false');

    $component->call('toggleDay', '2026-10-09');

    expect($day())->toBe('true');
});

test('malformed or past dates are ignored', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->call('toggleDay', '2026-02-31')
        ->call('toggleDay', 'not-a-date')
        ->call('toggleDay', '2026-10-01');

    expect($user->availabilities()->count())->toBe(0);
});

test('mark all weekends fills the remaining Fridays to Sundays of the month', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)->test(Calendar::class)->call('markWeekends');

    expect($user->availabilities()->orderBy('date')->pluck('date')->map->format('D j')->all())->toBe([
        'Fri 9', 'Sat 10', 'Sun 11',
        'Fri 16', 'Sat 17', 'Sun 18',
        'Fri 23', 'Sat 24', 'Sun 25',
        'Fri 30', 'Sat 31',
    ]);
});

test('mark all weekends works on the month being viewed', function () {
    $user = User::factory()->band()->create();

    Livewire::actingAs($user)->test(Calendar::class)->call('nextMonth')->call('markWeekends');

    expect($user->availabilities()->orderBy('date')->first()->date->format('Y-m-d'))->toBe('2026-11-01');
});

test('clear month only clears the month being viewed', function () {
    $user = User::factory()->band()->create();
    Availability::factory()->for($user)->create(['date' => '2026-10-16']);
    Availability::factory()->for($user)->create(['date' => '2026-11-06']);

    Livewire::actingAs($user)->test(Calendar::class)->call('clearMonth');

    expect($user->availabilities()->pluck('date')->map->format('Y-m-d')->all())->toBe(['2026-11-06']);
});

test('free days are shown on the calendar and in the side panel', function () {
    $user = User::factory()->band()->create();
    Availability::factory()->for($user)->create(['date' => '2026-10-16']);
    Availability::factory()->for(User::factory()->venue())->create(['date' => '2026-10-17']);

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSet('freeDays', ['2026-10-16' => true])
        ->assertSee('Fri 16 Oct')
        ->assertDontSee('Sat 17 Oct');
});

test('people with no free days are prompted to add some', function () {
    Livewire::actingAs(User::factory()->band()->create())
        ->test(Calendar::class)
        ->assertSee('You have not marked any free days yet.');
});
