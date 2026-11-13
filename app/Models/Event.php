<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Enums\Region;
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
 * @property Region|null $region Where artists are booked from; null means every region.
 * @property Carbon $starts_at
 * @property int $price Whole rand (ZAR); 0 means free.
 * @property int $artist_slots How many artists play, the host artist included.
 * @property EventStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['venue_profile_id', 'title', 'description', 'location', 'region', 'starts_at', 'price', 'artist_slots', 'status'])]
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
            'region' => Region::class,
            'starts_at' => 'datetime',
            'price' => 'integer',
            'artist_slots' => 'integer',
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
     * Artists asked to play at this event, and venues asked to host it.
     *
     * @return HasMany<BookingRequest, $this>
     */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /**
     * The artists playing: an artist hosting its own event is always on the line-up, followed by the
     * artists that accepted a booking request.
     *
     * @return Collection<int, User>
     */
    public function lineUp(): Collection
    {
        $booked = User::query()
            ->where('role', UserRole::Artist)
            ->whereHas('receivedBookingRequests', fn (Builder $query) => $query
                ->where('event_id', $this->id)
                ->where('status', BookingStatus::Accepted))
            ->with('artistProfile')
            ->orderBy('name')
            ->get();

        return $this->owner->role === UserRole::Artist
            ? $booked->prepend($this->owner->loadMissing('artistProfile'))
            : $booked;
    }

    /**
     * The artists asked to play who haven't answered yet.
     *
     * @return Collection<int, User>
     */
    public function invitedArtists(): Collection
    {
        return User::query()
            ->where('role', UserRole::Artist)
            ->whereHas('receivedBookingRequests', fn (Builder $query) => $query
                ->where('event_id', $this->id)
                ->where('status', BookingStatus::Requested)
                ->where('expires_at', '>', now()))
            ->with('artistProfile')
            ->orderBy('name')
            ->get();
    }

    /**
     * Artist slots already spoken for: the host artist (if an artist is hosting), artists that accepted, and
     * artists still deciding. Declined, expired and cancelled requests free their slot again.
     */
    public function takenArtistSlots(): int
    {
        $artists = $this->bookingRequests()->open()->whereRelation('recipient', 'role', UserRole::Artist)->count();

        return $artists + ($this->owner->role === UserRole::Artist ? 1 : 0);
    }

    /**
     * How many more artists can be asked to play.
     */
    public function openArtistSlots(): int
    {
        return max(0, $this->artist_slots - $this->takenArtistSlots());
    }

    /**
     * What still has to be confirmed before the event can be published: every artist slot filled by an
     * artist that accepted (a hosting artist counts as one), and no venue still deciding. A draft is a
     * pending event; there is no point announcing one without its line-up and venue.
     *
     * @param  int|null  $artistSlots  The artist slot count to check against; defaults to the saved one.
     * @return list<string> Reasons, empty when the event is ready to publish.
     */
    public function publishBlockers(?int $artistSlots = null): array
    {
        $artistSlots ??= $this->artist_slots;
        $blockers = [];

        $confirmed = $this->bookingRequests()
            ->where('status', BookingStatus::Accepted)
            ->whereRelation('recipient', 'role', UserRole::Artist)
            ->count() + ($this->owner->role === UserRole::Artist ? 1 : 0);

        if ($confirmed < $artistSlots) {
            $blockers[] = trans_choice(':confirmed of :count artist confirmed.|:confirmed of :count artists confirmed.', $artistSlots, ['confirmed' => $confirmed, 'count' => $artistSlots]);
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
     * Whether this is a draft, still to come, with everything confirmed: it only needs publishing.
     */
    public function canBePublished(): bool
    {
        return $this->status === EventStatus::Draft && ! $this->isPast() && $this->isReadyToPublish();
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
