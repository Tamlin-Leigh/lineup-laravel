<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

/**
 * Whether an event is past or upcoming is derived from its date, not stored here.
 */
enum EventStatus: string
{
    use HasEnumOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
        };
    }
}
