<?php

namespace App\Jobs;

use App\Actions\SendMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\UnreadMessages;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a while after a message is sent: if the person still hasn't read it, email them once about
 * everything unread in that chat. Unique per chat and person, so a burst of messages leads to one
 * email rather than one per message.
 */
class AlertUnreadMessages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public Conversation $conversation, public User $user) {}

    public function uniqueId(): string
    {
        return $this->conversation->id.':'.$this->user->id;
    }

    /** Released once it runs; held long enough to cover the wait before it does. */
    public function uniqueFor(): int
    {
        return (SendMessage::ALERT_AFTER_MINUTES + 5) * 60;
    }

    public function handle(): void
    {
        $conversation = $this->conversation->fresh();

        if ($conversation === null || ! $conversation->hasParticipant($this->user)) {
            return;
        }

        $side = $conversation->sideOf($this->user);
        $alertedAt = $conversation->{$side.'_alerted_at'};

        // Only messages since the last email count, so nobody is emailed twice about the same ones.
        $unread = $conversation->unreadMessagesFor($this->user)
            ->when($alertedAt, fn ($query) => $query->where('created_at', '>', $alertedAt))
            ->whereNotNull('user_id')
            ->count();

        if ($unread === 0) {
            return;
        }

        $this->user->notify(new UnreadMessages($conversation, $unread));
        $conversation->forceFill([$side.'_alerted_at' => now()])->save();
    }
}
