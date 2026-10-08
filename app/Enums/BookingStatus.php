<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum BookingStatus: string
{
    use HasEnumOptions;

    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * The single source of truth for which status changes are allowed.
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Requested => [self::Accepted, self::Declined, self::Cancelled],
            self::Accepted => [self::Cancelled],
            self::Declined, self::Cancelled => [],
        }, true);
    }
}
