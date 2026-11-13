<?php

namespace App\Models;

use App\Enums\Region;
use Database\Factories\OrganiserProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $org_name
 * @property string $contact_number
 * @property string $contact_email
 * @property Region|null $region Null only for organisers who signed up before organisers had regions.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['org_name', 'contact_number', 'contact_email', 'region'])]
class OrganiserProfile extends Model
{
    /** @use HasFactory<OrganiserProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region' => Region::class,
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
     * @return MorphMany<Link, $this>
     */
    public function links(): MorphMany
    {
        return $this->morphMany(Link::class, 'linkable');
    }
}
