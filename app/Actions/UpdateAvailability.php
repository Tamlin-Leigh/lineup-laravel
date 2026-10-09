<?php

namespace App\Actions;

use App\Models\Availability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Marks days as free or busy for an account. Only today onwards can change, and no more
 * than two years ahead, so the calendar can't be filled with stale or absurd dates.
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
            return false;
        }

        $this->markFree($user, [$day]);

        return true;
    }

    /**
     * Mark several days as free, skipping any already marked or outside the allowed range.
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

        return $rows === [] ? 0 : Availability::query()->insertOrIgnore(array_values($rows));
    }

    /**
     * Clear free days between two dates (inclusive), leaving past days untouched.
     *
     * @return int How many days were cleared.
     */
    public function clear(User $user, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = CarbonImmutable::parse($from)->max(today());

        return $user->availabilities()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->delete();
    }

    private function isFree(User $user, CarbonInterface $day): bool
    {
        return $user->availabilities()->whereDate('date', $day->toDateString())->exists();
    }
}
