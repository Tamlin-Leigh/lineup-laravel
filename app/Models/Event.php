<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $venue_profile_id
 * @property string $title
 * @property string|null $description
 * @property EventCategory $category
 * @property string $location
 * @property Carbon $starts_at
 * @property int $price Whole rand (ZAR); 0 means free.
 * @property EventStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['venue_profile_id', 'title', 'description', 'category', 'location', 'starts_at', 'price', 'status'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * The tabs on the events page, in order. "Active" and "Past" are published events either side of now.
     */
    public const TABS = [
        'active' => 'Active',
        'draft' => 'Draft',
        'past' => 'Past',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => EventCategory::class,
            'starts_at' => 'datetime',
            'price' => 'integer',
            'status' => EventStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<VenueProfile, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(VenueProfile::class, 'venue_profile_id');
    }

    /**
     * Bands asked to play at this event, and venues asked to host it.
     *
     * @return HasMany<BookingRequest, $this>
     */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /**
     * The bands playing: a band hosting its own event is always on the line-up, followed by the
     * bands that accepted a booking request.
     *
     * @return Collection<int, User>
     */
    public function lineUp(): Collection
    {
        $booked = User::query()
            ->where('role', UserRole::Band)
            ->whereHas('receivedBookingRequests', fn (Builder $query) => $query
                ->where('event_id', $this->id)
                ->where('status', BookingStatus::Accepted))
            ->with('bandProfile')
            ->orderBy('name')
            ->get();

        return $this->owner->role === UserRole::Band
            ? $booked->prepend($this->owner->loadMissing('bandProfile'))
            : $booked;
    }

    /**
     * An event has one venue, so a venue can only be asked while the event has none and no other
     * venue is still considering (or has accepted) a request for it.
     */
    public function needsVenue(): bool
    {
        return $this->venue_profile_id === null
            && ! $this->bookingRequests()->open()->whereRelation('recipient', 'role', UserRole::Venue)->exists();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->where('starts_at', '<', now())->orderByDesc('starts_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }

    /**
     * Events the user is taking part in: ones they host, and ones they accepted a booking for.
     * Cancelled events are left out.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where('status', '!=', EventStatus::Cancelled)
            ->where(fn (Builder $query) => $query
                ->whereBelongsTo($user, 'owner')
                ->orWhereHas('bookingRequests', fn (Builder $requests) => $requests
                    ->where('recipient_id', $user->id)
                    ->where('status', BookingStatus::Accepted)));
    }

    /**
     * Restrict to one of the events page tabs (see TABS).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'draft' => $query->where('status', EventStatus::Draft)->orderBy('starts_at'),
            'past' => $query->published()->past(),
            'cancelled' => $query->where('status', EventStatus::Cancelled)->orderByDesc('starts_at'),
            default => $query->published()->upcoming(),
        };
    }

    public function isPast(): bool
    {
        return $this->starts_at->isPast();
    }

    public function priceLabel(): string
    {
        return $this->price > 0 ? 'R'.number_format($this->price) : __('Free');
    }
}
