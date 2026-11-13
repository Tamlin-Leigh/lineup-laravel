<?php

namespace App\Models;

use App\Enums\Region;
use Database\Factories\ArtistProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $manager_name
 * @property string $manager_phone
 * @property string $manager_email
 * @property string $artist_name
 * @property int $member_count
 * @property string $member_names
 * @property string $genre
 * @property Region|null $region Null only for accounts created before regions existed.
 * @property string|null $bio
 * @property int|null $booking_fee Whole rand (ZAR); null when the artist hasn't set one.
 * @property bool $accepts_door_split Open to playing for a share of the door or ticket sales.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'manager_name', 'manager_phone', 'manager_email',
    'artist_name', 'member_count', 'member_names', 'genre', 'region', 'bio',
    'booking_fee', 'accepts_door_split',
])]
class ArtistProfile extends Model
{
    /** @use HasFactory<ArtistProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
            'region' => Region::class,
            'booking_fee' => 'integer',
            'accepts_door_split' => 'boolean',
        ];
    }

    /**
     * The artist's terms in a few words for hosts picking artists, e.g. "R2,500 fee · Open to door splits".
     */
    public function bookingTerms(): string
    {
        $terms = array_filter([
            $this->booking_fee !== null ? (string) __('R:fee fee', ['fee' => number_format($this->booking_fee)]) : null,
            $this->accepts_door_split ? (string) __('Open to door / ticket splits') : null,
        ]);

        return $terms !== [] ? implode(' · ', $terms) : (string) __('Fee on request');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<Link, $this>
     */
    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable');
    }
}
