<?php

namespace App\Actions;

use App\Models\Availability;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Marks days as free or busy for an account. Only today onwards can change, and no more
 * than two years ahead, so the calendar can't be filled with stale or absurd dates. A day the
 * account is hosting or booked for an event can't be marked free.
 */
class UpdateAvailability
{
    public const MAX_DAYS_AHEAD = 730;

    public function canChange(CarbonInterface $day): bool
    {
        $day = CarbonImmutable::parse($day)->startOfDay();

        return $day->gte(today()) && $day->lte(today()->addDays(self::MAX_DAYS_AHEAD));
    }

    /**
     * Flip one day between free and busy. Returns whether the day is now free.
     */
    public function toggle(User $user, CarbonInterface $day): bool
    {
        if (! $this->canChange($day)) {
            return $this->isFree($user, $day);
        }

        $deleted = $user->availabilities()->whereDate('date', $day->toDateString())->delete();

        if ($deleted > 0) {
            $this->touch($user);

            return false;
        }

        return $this->markFree($user, [$day]) > 0;
    }

    /**
     * Mark several days as free, skipping any already marked, outside the allowed range, or on
     * which the user is hosting or booked for an event.
     *
     * @param  iterable<CarbonInterface>  $days
     * @return int How many days were newly marked.
     */
    public function markFree(User $user, iterable $days): int
    {
        $now = now();
        $rows = [];

        foreach ($days as $day) {
            if ($this->canChange($day)) {
                $rows[$day->toDateString()] = ['user_id' => $user->id, 'date' => $day->toDateString(), 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if ($rows === []) {
            return 0;
        }

        $dates = array_keys($rows);
        $rows = array_diff_key($rows, $this->busyDays($user, min($dates), max($dates)));

        $marked = $rows === [] ? 0 : Availability::query()->insertOrIgnore(array_values($rows));

        if ($marked > 0) {
            $this->touch($user);
        }

        return $marked;
    }

    /**
     * Clear particular free days, leaving past days untouched.
     *
     * @param  iterable<CarbonInterface>  $days
     * @return int How many days were cleared.
     */
    public function remove(User $user, iterable $days): int
    {
        $dates = collect($days)
            ->filter(fn (CarbonInterface $day) => $this->canChange($day))
            ->mapWithKeys(fn (CarbonInterface $day) => [$day->toDateString() => true]);

        if ($dates->isEmpty()) {
            return 0;
        }

        // Match on the formatted date rather than whereIn(), for the SQLite time-part reason in Availability::upcoming().
        $ids = $user->availabilities()
            ->whereDate('date', '>=', $dates->keys()->min())
            ->whereDate('date', '<=', $dates->keys()->max())
            ->get(['id', 'date'])
            ->filter(fn (Availability $availability) => $dates->has($availability->date->toDateString()))
            ->modelKeys();

        $removed = $ids === [] ? 0 : Availability::query()->whereKey($ids)->delete();

        if ($removed > 0) {
            $this->touch($user);
        }

        return $removed;
    }

    /**
     * Days the user is hosting or booked for an event, as a Y-m-d => true lookup.
     *
     * @return array<string, bool>
     */
    public function busyDays(User $user, string $from, string $to): array
    {
        return Event::query()
            ->involving($user)
            ->whereDate('starts_at', '>=', $from)
            ->whereDate('starts_at', '<=', $to)
            ->pluck('starts_at')
            ->mapWithKeys(fn (CarbonInterface $startsAt) => [$startsAt->format('Y-m-d') => true])
            ->all();
    }

    /**
     * Hosting (or being booked for) an event on a day means the day is no longer free.
     */
    public function markBusy(User $user, CarbonInterface $day): void
    {
        $user->availabilities()->whereDate('date', $day->toDateString())->delete();
    }

    /**
     * Clear free days between two dates (inclusive), leaving past days untouched.
     *
     * @return int How many days were cleared.
     */
    public function clear(User $user, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = CarbonImmutable::parse($from)->max(today());

        $cleared = $user->availabilities()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->delete();

        if ($cleared > 0) {
            $this->touch($user);
        }

        return $cleared;
    }

    /**
     * Days between two dates on which the user has a booking request waiting for their reply,
     * as Y-m-d => event titles. Clearing such a day doesn't decline the request, so callers warn.
     *
     * @return array<string, list<string>>
     */
    public function pendingRequestDays(User $user, string $from, string $to): array
    {
        return $user->receivedBookingRequests()
            ->awaitingReply()
            ->whereHas('event', fn ($query) => $query
                ->whereDate('starts_at', '>=', $from)
                ->whereDate('starts_at', '<=', $to))
            ->with('event')
            ->get()
            ->groupBy(fn (BookingRequest $booking) => $booking->event->starts_at->format('Y-m-d'))
            ->map(fn ($bookings) => array_values($bookings->map(fn (BookingRequest $booking) => $booking->event->title)->all()))
            ->all();
    }

    /**
     * Record that the user changed their free days themselves (not as a side effect of an event),
     * so profiles and Discover can show how current they are.
     */
    private function touch(User $user): void
    {
        $user->forceFill(['availability_updated_at' => now()])->saveQuietly();
    }

    private function isFree(User $user, CarbonInterface $day): bool
    {
        return $user->availabilities()->whereDate('date', $day->toDateString())->exists();
    }
}
