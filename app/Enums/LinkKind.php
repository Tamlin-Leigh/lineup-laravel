<?php

namespace App\Enums;

use App\Concerns\HasEnumOptions;

enum LinkKind: string
{
    use HasEnumOptions;

    case Music = 'music';
    case Social = 'social';

    public function label(): string
    {
        return match ($this) {
            self::Music => 'Music',
            self::Social => 'Social',
        };
    }

    /**
     * Platforms that make sense for this kind of link.
     *
     * @return list<LinkPlatform>
     */
    public function platforms(): array
    {
        return match ($this) {
            self::Music => [
                LinkPlatform::Spotify,
                LinkPlatform::AppleMusic,
                LinkPlatform::SoundCloud,
                LinkPlatform::YouTube,
                LinkPlatform::Bandcamp,
                LinkPlatform::Other,
            ],
            self::Social => [
                LinkPlatform::Instagram,
                LinkPlatform::Facebook,
                LinkPlatform::TikTok,
                LinkPlatform::X,
                LinkPlatform::Website,
                LinkPlatform::Other,
            ],
        };
    }
}
