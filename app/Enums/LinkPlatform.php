<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum LinkPlatform: string
{
    use HasEnumOptions;

    case Spotify = 'spotify';
    case AppleMusic = 'apple-music';
    case SoundCloud = 'soundcloud';
    case YouTube = 'youtube';
    case Bandcamp = 'bandcamp';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case X = 'x';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spotify => 'Spotify',
            self::AppleMusic => 'Apple Music',
            self::SoundCloud => 'SoundCloud',
            self::YouTube => 'YouTube',
            self::Bandcamp => 'Bandcamp',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::X => 'X / Twitter',
            self::Website => 'Website',
            self::Other => 'Other',
        };
    }
}
