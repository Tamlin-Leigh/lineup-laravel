<?php

namespace App\Models;

use App\Enums\EventDay;
use App\Enums\Region;
use App\Enums\SoundEngineer;
use Database\Factories\VenueProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $user_id
 * @property string $manager_name
 * @property string $manager_phone
 * @property string $manager_email
 * @property int $capacity
 * @property float|null $stage_width Metres; null when the venue hasn't given a stage size.
 * @property float|null $stage_depth Metres; null when the venue hasn't given a stage size.
 * @property string $location
 * @property Region|null $region Null only for accounts created before regions existed.
 * @property int $min_door_charge Whole rand (ZAR).
 * @property SoundEngineer $sound_engineer
 * @property string|null $sound_engineer_name
 * @property bool $has_own_equipment
 * @property string|null $equipment_details
 * @property Collection<int, EventDay> $event_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'manager_name', 'manager_phone', 'manager_email',
    'capacity', 'stage_width', 'stage_depth', 'location', 'region', 'min_door_charge',
    'sound_engineer', 'sound_engineer_name', 'has_own_equipment', 'equipment_details',
    'event_days',
])]
class VenueProfile extends Model
{
    /** @use HasFactory<VenueProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'stage_width' => 'float',
            'stage_depth' => 'float',
            'region' => Region::class,
            'min_door_charge' => 'integer',
            'sound_engineer' => SoundEngineer::class,
            'has_own_equipment' => 'boolean',
            'event_days' => AsEnumCollection::of(EventDay::class),
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The stage as width by depth, e.g. "6 m × 4.5 m", or null if the venue hasn't given one.
     */
    public function stageSize(): ?string
    {
        return $this->stage_width !== null && $this->stage_depth !== null
            ? "{$this->stage_width} m × {$this->stage_depth} m"
            : null;
    }

    /**
     * Events taking place at this venue, whoever created them.
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Social links only: venues have no music links.
     *
     * @return MorphMany<Link, $this>
     */
    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable');
    }
}
