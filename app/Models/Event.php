<?php

namespace App\Models;

use App\Enums\BookingStatus;
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
 * @property string $location
 * @property Carbon $starts_at
 * @property int $price Whole rand (ZAR); 0 means free.
 * @property int $band_slots How many bands play, the host band included.
 * @property EventStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['venue_profile_id', 'title', 'description', 'location', 'starts_at', 'price', 'band_slots', 'status'])]
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
            'starts_at' => 'datetime',
            'price' => 'integer',
            'band_slots' => 'integer',
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
     * Band slots already spoken for: the host band (if a band is hosting), bands that accepted, and
     * bands still deciding. Declined, expired and cancelled requests free their slot again.
     */
    public function takenBandSlots(): int
    {
        $bands = $this->bookingRequests()->open()->whereRelation('recipient', 'role', UserRole::Band)->count();

        return $bands + ($this->owner->role === UserRole::Band ? 1 : 0);
    }

    /**
     * How many more bands can be asked to play.
     */
    public function openBandSlots(): int
    {
        return max(0, $this->band_slots - $this->takenBandSlots());
    }

    /**
     * What still has to be confirmed before the event can be published: every band slot filled by a
     * band that accepted (a hosting band counts as one), and no venue still deciding. A draft is a
     * pending event; there is no point announcing one without its line-up and venue.
     *
     * @param  int|null  $bandSlots  The band slot count to check against; defaults to the saved one.
     * @return list<string> Reasons, empty when the event is ready to publish.
     */
    public function publishBlockers(?int $bandSlots = null): array
    {
        $bandSlots ??= $this->band_slots;
        $blockers = [];

        $confirmed = $this->bookingRequests()
            ->where('status', BookingStatus::Accepted)
            ->whereRelation('recipient', 'role', UserRole::Band)
            ->count() + ($this->owner->role === UserRole::Band ? 1 : 0);

        if ($confirmed < $bandSlots) {
            $blockers[] = trans_choice(':confirmed of :count band confirmed.|:confirmed of :count bands confirmed.', $bandSlots, ['confirmed' => $confirmed, 'count' => $bandSlots]);
        }

        $pendingVenue = $this->bookingRequests()
            ->awaitingReply()
            ->whereRelation('recipient', 'role', UserRole::Venue)
            ->with('recipient')
            ->first();

        if ($pendingVenue !== null) {
            $blockers[] = __('Waiting for :venue to confirm.', ['venue' => $pendingVenue->recipient->displayName()]);
        }

        return $blockers;
    }

    public function isReadyToPublish(): bool
    {
        return $this->publishBlockers() === [];
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
