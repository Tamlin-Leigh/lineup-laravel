<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed over Reverb the moment a message is posted, so an open chat shows it straight away and
 * both participants' unread badges update. Sent immediately rather than queued: a chat message
 * shouldn't wait behind queued emails.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * The chat itself, plus each participant's own channel (for the sidebar badge and chat list).
     *
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;

        return [
            new PrivateChannel('conversations.'.$conversation->id),
            new PrivateChannel('users.'.$conversation->owner_id),
            new PrivateChannel('users.'.$conversation->recipient_id),
        ];
    }

    /**
     * Only ids go over the wire; the page fetches the message itself, through the usual access checks.
     *
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
        ];
    }
}
