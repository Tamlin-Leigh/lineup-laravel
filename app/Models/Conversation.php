<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * The private chat for one accepted booking: the event's owner and the band or venue that
 * accepted. It turns read-only once the booking or event is cancelled or the event has happened.
 *
 * @property int $id
 * @property int $booking_request_id
 * @property int $owner_id
 * @property int $recipient_id
 * @property CarbonImmutable|null $owner_read_at
 * @property CarbonImmutable|null $recipient_read_at
 * @property CarbonImmutable|null $owner_alerted_at
 * @property CarbonImmutable|null $recipient_alerted_at
 * @property CarbonImmutable|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_request_id', 'owner_id', 'recipient_id', 'owner_read_at', 'recipient_read_at', 'owner_alerted_at', 'recipient_alerted_at', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_read_at' => 'datetime',
            'recipient_read_at' => 'datetime',
            'owner_alerted_at' => 'datetime',
            'recipient_alerted_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * The chat for a booking, started the first time it's needed.
     */
    public static function forBooking(BookingRequest $booking): self
    {
        return self::firstOrCreate(
            ['booking_request_id' => $booking->id],
            ['owner_id' => $booking->event->user_id, 'recipient_id' => $booking->recipient_id],
        );
    }

    /**
     * @return BelongsTo<BookingRequest, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'booking_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Chats the user is in, most recently active first.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->where('owner_id', $user->id)->orWhere('recipient_id', $user->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    public function hasParticipant(User $user): bool
    {
        return in_array($user->id, [$this->owner_id, $this->recipient_id], true);
    }

    /**
     * The person on the other side of the chat from the given participant.
     */
    public function otherParticipant(User $user): User
    {
        return $user->id === $this->owner_id ? $this->recipient : $this->owner;
    }

    /**
     * Which side of the chat a participant is on, for the per-side columns.
     */
    public function sideOf(User $user): string
    {
        return $user->id === $this->owner_id ? 'owner' : 'recipient';
    }

    public function readAtFor(User $user): ?CarbonImmutable
    {
        return $this->{$this->sideOf($user).'_read_at'};
    }

    public function markReadFor(User $user): void
    {
        $this->forceFill([$this->sideOf($user).'_read_at' => now()])->save();
    }

    /**
     * Messages the user hasn't seen yet: anything from the other side (or BandLink) since they
     * last opened the chat.
     *
     * @return HasMany<Message, $this>
     */
    public function unreadMessagesFor(User $user): HasMany
    {
        $readAt = $this->readAtFor($user);
        $messages = $this->messages()
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $user->id));

        if ($readAt !== null) {
            $messages->where('created_at', '>', $readAt);
        }

        return $messages;
    }

    /**
     * Why nobody can send messages any more, or null while the chat is open.
     */
    public function readOnlyReason(): ?string
    {
        $booking = $this->booking;
        $event = $booking->event;

        return match (true) {
            $event->status === EventStatus::Cancelled => (string) __('This event was cancelled, so the chat is read-only.'),
            $booking->status !== BookingStatus::Accepted => (string) __('This booking was cancelled, so the chat is read-only.'),
            $event->isPast() => (string) __('This event has happened, so the chat is read-only.'),
            default => null,
        };
    }

    public function isReadOnly(): bool
    {
        return $this->readOnlyReason() !== null;
    }
}
