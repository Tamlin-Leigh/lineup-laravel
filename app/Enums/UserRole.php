<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum UserRole: string
{
    use HasEnumOptions;

    case Venue = 'venue';
    case Artist = 'artist';
    case Organiser = 'organiser';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Venue => 'Venue',
            self::Artist => 'Artist',
            self::Organiser => 'Organiser',
            self::Admin => 'Admin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Venue => 'I manage a venue for live music',
            self::Artist => "I'm a solo musician or in a group",
            self::Organiser => 'I organise music events',
            self::Admin => 'Platform administrator',
        };
    }

    /**
     * Roles a visitor may pick for themselves at sign-up. Admin is never self-assigned.
     *
     * @return list<self>
     */
    public static function registrable(): array
    {
        return [self::Venue, self::Artist, self::Organiser];
    }
}
