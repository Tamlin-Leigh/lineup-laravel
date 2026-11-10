<?php

use App\Actions\UpdateAvailability;
use App\Models\Availability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 09:00');
    $this->action = app(UpdateAvailability::class);
    $this->user = User::factory()->artist()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return list<string>
 */
function freeDates(User $user): array
{
    return $user->availabilities()->orderBy('date')->pluck('date')->map->format('Y-m-d')->all();
}

test('toggling a day marks it free and then clears it', function () {
    $day = CarbonImmutable::parse('2026-10-09');

    expect($this->action->toggle($this->user, $day))->toBeTrue()
        ->and(freeDates($this->user))->toBe(['2026-10-09'])
        ->and($this->action->toggle($this->user, $day))->toBeFalse()
        ->and(freeDates($this->user))->toBe([]);
});

test('past days and days more than two years ahead cannot change', function () {
    expect($this->action->toggle($this->user, CarbonImmutable::parse('2026-10-07')))->toBeFalse()
        ->and($this->action->toggle($this->user, CarbonImmutable::parse('2028-10-08')))->toBeFalse()
        ->and(freeDates($this->user))->toBe([]);
});

test('today can still be marked free', function () {
    expect($this->action->toggle($this->user, CarbonImmutable::parse('2026-10-08')))->toBeTrue();
});

test('marking several days skips duplicates and days out of range', function () {
    $this->action->markFree($this->user, [CarbonImmutable::parse('2026-10-09')]);

    $added = $this->action->markFree($this->user, [
        CarbonImmutable::parse('2026-10-01'),
        CarbonImmutable::parse('2026-10-09'),
        CarbonImmutable::parse('2026-10-10'),
        CarbonImmutable::parse('2026-10-10'),
    ]);

    expect($added)->toBe(1)
        ->and(freeDates($this->user))->toBe(['2026-10-09', '2026-10-10']);
});

test('clearing a range keeps past days and days outside it', function () {
    Availability::factory()->for($this->user)->create(['date' => '2026-10-02']);
    $this->action->markFree($this->user, [
        CarbonImmutable::parse('2026-10-09'),
        CarbonImmutable::parse('2026-10-31'),
        CarbonImmutable::parse('2026-11-01'),
    ]);

    $cleared = $this->action->clear($this->user, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($cleared)->toBe(2)
        ->and(freeDates($this->user))->toBe(['2026-10-02', '2026-11-01']);
});

test("one user's changes never touch another user's days", function () {
    $other = User::factory()->venue()->create();
    $this->action->markFree($other, [CarbonImmutable::parse('2026-10-09')]);

    $this->action->toggle($this->user, CarbonImmutable::parse('2026-10-09'));
    $this->action->clear($this->user, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect(freeDates($other))->toBe(['2026-10-09']);
});

test('deleting an account removes its free days', function () {
    $this->action->markFree($this->user, [CarbonImmutable::parse('2026-10-09')]);

    $this->user->delete();

    expect(Availability::count())->toBe(0);
});

test('remove clears only the given future days', function () {
    foreach (['2026-10-09', '2026-10-10', '2026-10-16'] as $date) {
        Availability::factory()->for($this->user)->create(['date' => $date]);
    }

    expect($this->action->remove($this->user, [CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-10-16'), CarbonImmutable::parse('2026-10-01')]))->toBe(2)
        ->and(freeDates($this->user))->toBe(['2026-10-09']);
});

test('changing free days records when, but an event clearing a day does not', function () {
    $this->action->markBusy($this->user, CarbonImmutable::parse('2026-10-09'));

    expect($this->user->fresh()->availability_updated_at)->toBeNull();

    $this->action->toggle($this->user, CarbonImmutable::parse('2026-10-09'));

    expect($this->user->fresh()->availability_updated_at->toDateTimeString())->toBe('2026-10-08 09:00:00');
});
