<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum SoundEngineer: string
{
    use HasEnumOptions;

    case InHouse = 'in-house';
    case Outsource = 'outsource';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::InHouse => 'In-house',
            self::Outsource => 'Outsource',
            self::None => 'None / Not applicable',
        };
    }
}
