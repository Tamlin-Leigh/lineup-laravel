<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

/**
 * Where a request to book a band for an event stands. A request the band hasn't answered within
 * its deadline counts as expired even before the scheduled `bookings:expire` run stores it.
 */
enum BookingStatus: string
{
    use HasEnumOptions;

    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting reply',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'No reply',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Tailwind classes for the status pill.
     */
    public function pillClasses(): string
    {
        return match ($this) {
            self::Requested => 'bg-amber-100 text-amber-800',
            self::Accepted => 'bg-emerald-100 text-emerald-800',
            self::Declined => 'bg-red-100 text-red-800',
            self::Expired, self::Cancelled => 'bg-zinc-100 text-zinc-600',
        };
    }

    /**
     * The single source of truth for which status changes are allowed.
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Requested => [self::Accepted, self::Declined, self::Expired, self::Cancelled],
            self::Accepted => [self::Cancelled],
            self::Declined, self::Expired, self::Cancelled => [],
        }, true);
    }
}
