<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum EventCategory: string
{
    use HasEnumOptions;

    case HeadlineShow = 'headline-show';
    case Festival = 'festival';
    case AcousticSet = 'acoustic-set';
    case SupportSlot = 'support-slot';
    case PrivateEvent = 'private-event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HeadlineShow => 'Headline Show',
            self::Festival => 'Festival',
            self::AcousticSet => 'Acoustic Set',
            self::SupportSlot => 'Support Slot',
            self::PrivateEvent => 'Private Event',
            self::Other => 'Other',
        };
    }

    /**
     * Gradient colours for cards and calendar chips, carried over from the prototype.
     *
     * @return array{0: string, 1: string}
     */
    public function accent(): array
    {
        return match ($this) {
            self::HeadlineShow => ['#7c3aed', '#c026d3'],
            self::Festival => ['#2563eb', '#06b6d4'],
            self::AcousticSet => ['#f59e0b', '#f97316'],
            self::SupportSlot => ['#db2777', '#ec4899'],
            self::PrivateEvent => ['#64748b', '#94a3b8'],
            self::Other => ['#184e6c', '#5ba3c6'],
        };
    }
}
