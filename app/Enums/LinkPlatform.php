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

    /**
     * The Simple Icons brand logo (blade-simple-icons component), or null for the generic platforms.
     */
    public function brandIcon(): ?string
    {
        return match ($this) {
            self::Spotify => 'si-spotify',
            self::AppleMusic => 'si-applemusic',
            self::SoundCloud => 'si-soundcloud',
            self::YouTube => 'si-youtube',
            self::Bandcamp => 'si-bandcamp',
            self::Instagram => 'si-instagram',
            self::Facebook => 'si-facebook',
            self::TikTok => 'si-tiktok',
            self::X => 'si-x',
            self::Website, self::Other => null,
        };
    }

    /**
     * The Flux (Heroicons) icon used when there is no brand logo.
     */
    public function fallbackIcon(): string
    {
        return $this === self::Website ? 'globe-alt' : 'link';
    }

    /**
     * The platform's brand colour, for its icon.
     */
    public function color(): string
    {
        return match ($this) {
            self::Spotify => '#1DB954',
            self::AppleMusic => '#FA243C',
            self::SoundCloud => '#FF5500',
            self::YouTube => '#FF0000',
            self::Bandcamp => '#408294',
            self::Instagram => '#E4405F',
            self::Facebook => '#0866FF',
            self::TikTok, self::X => '#000000',
            self::Website, self::Other => '#184e6c',
        };
    }
}
