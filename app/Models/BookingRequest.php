<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookingRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An event's owner asking an artist to play at it, or a venue to host it (see User::bookableRoles()).
 * The recipient has REPLY_DAYS (or until the event starts, if sooner) to accept or decline.
 *
 * @property int $id
 * @property int $event_id
 * @property int $recipient_id
 * @property BookingStatus $status
 * @property string|null $message
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['recipient_id', 'status', 'message', 'expires_at', 'responded_at'])]
class BookingRequest extends Model
{
    /** @use HasFactory<BookingRequestFactory> */
    use HasFactory;

    public const REPLY_DAYS = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The artist or venue that was asked.
     *
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * When a request sent now for this event must be answered by.
     */
    public static function deadlineFor(Event $event): CarbonImmutable
    {
        return now()->addDays(self::REPLY_DAYS)->min($event->starts_at);
    }

    /**
     * Requests the recipient can still answer, the most urgent first.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingReply(Builder $query): void
    {
        $query->where('status', BookingStatus::Requested)->where('expires_at', '>', now())->orderBy('expires_at');
    }

    /**
     * Requests still in play: waiting for a reply or accepted.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', BookingStatus::Accepted)
            ->orWhere(fn (Builder $query) => $query->where('status', BookingStatus::Requested)->where('expires_at', '>', now())));
    }

    /**
     * Unanswered requests whose deadline has passed but which are not yet stored as expired.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', BookingStatus::Requested)->where('expires_at', '<=', now());
    }

    public function isAwaitingReply(): bool
    {
        return $this->status === BookingStatus::Requested && $this->expires_at->isFuture();
    }

    /**
     * The status to show: an unanswered request past its deadline reads as expired.
     */
    public function currentStatus(): BookingStatus
    {
        return $this->status === BookingStatus::Requested && ! $this->isAwaitingReply()
            ? BookingStatus::Expired
            : $this->status;
    }
}
