<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\BookingRequested;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property UserRole $role
 * @property string|null $profile_photo_path
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * `role` is deliberately not fillable: it is only ever set by RegisterUser (or a seeder),
 * so no request payload can promote an account.
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        // Profiles and links cascade in the database; the photo file has to be removed by hand.
        static::deleted(function (User $user): void {
            if ($user->profile_photo_path !== null) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Events this user created.
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Days this user has marked as free for a gig.
     *
     * @return HasMany<Availability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /**
     * Requests from other accounts to play at (bands) or host (venues) their events.
     *
     * @return HasMany<BookingRequest, $this>
     */
    public function receivedBookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'recipient_id');
    }

    /**
     * Who this account can ask to take part in its events. Every host books bands; bands and
     * organisers also need a venue, while a venue's events are at its own venue.
     *
     * @return list<UserRole>
     */
    public function bookableRoles(): array
    {
        return match ($this->role) {
            UserRole::Venue => [UserRole::Band],
            UserRole::Band, UserRole::Organiser => [UserRole::Venue, UserRole::Band],
            UserRole::Admin => [],
        };
    }

    public function canBook(UserRole $role): bool
    {
        return in_array($role, $this->bookableRoles(), true);
    }

    /**
     * Bands and venues are booked; organisers only book others.
     */
    public function canReceiveBookingRequests(): bool
    {
        return in_array($this->role, [UserRole::Band, UserRole::Venue], true);
    }

    /**
     * The number on the sidebar's Bookings item: requests still waiting for this account's reply,
     * plus booking updates (replies, expiries, cancellations) it hasn't seen yet.
     */
    public function bookingsNeedingAttention(): int
    {
        return $this->receivedBookingRequests()->awaitingReply()->count()
            + $this->unreadNotifications()->where('type', '!=', BookingRequested::class)->count();
    }

    /**
     * Messages in this user's booking chats they haven't seen: anything from the other side (or a
     * BandLink note) posted since they last opened that chat.
     */
    public function unreadMessagesCount(): int
    {
        $unreadOn = fn (string $side) => fn (Builder $query) => $query
            ->where($side.'_id', $this->id)
            ->where(fn (Builder $query) => $query
                ->whereNull($side.'_read_at')
                ->orWhereColumn('messages.created_at', '>', 'conversations.'.$side.'_read_at'));

        return Message::query()
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $this->id))
            ->whereHas('conversation', fn (Builder $query) => $query->where($unreadOn('owner'))->orWhere($unreadOn('recipient')))
            ->count();
    }

    /**
     * @return HasOne<VenueProfile, $this>
     */
    public function venueProfile(): HasOne
    {
        return $this->hasOne(VenueProfile::class);
    }

    /**
     * @return HasOne<BandProfile, $this>
     */
    public function bandProfile(): HasOne
    {
        return $this->hasOne(BandProfile::class);
    }

    /**
     * @return HasOne<OrganiserProfile, $this>
     */
    public function organiserProfile(): HasOne
    {
        return $this->hasOne(OrganiserProfile::class);
    }

    /**
     * The name others see: the band's or organisation's name where there is one.
     */
    public function displayName(): string
    {
        return match ($this->role) {
            UserRole::Band => $this->bandProfile->band_name ?? $this->name,
            UserRole::Organiser => $this->organiserProfile->org_name ?? $this->name,
            default => $this->name,
        };
    }

    /**
     * Public URL of the user's profile photo, if they have uploaded one.
     */
    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path !== null
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
