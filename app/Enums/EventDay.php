<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum EventDay: string
{
    use HasEnumOptions;

    case Weekdays = 'weekdays';
    case Weekend = 'weekend';

    public function label(): string
    {
        return match ($this) {
            self::Weekdays => 'Weekdays',
            self::Weekend => 'Weekend',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Weekdays => 'Monday to Thursday',
            self::Weekend => 'Friday to Sunday',
        };
    }
}
