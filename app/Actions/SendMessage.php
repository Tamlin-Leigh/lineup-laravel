<?php

namespace App\Actions;

use App\Events\MessageSent;
use App\Jobs\AlertUnreadMessages;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class SendMessage
{
    /** How long a message can sit unread before the other person is emailed about it. */
    public const ALERT_AFTER_MINUTES = 15;

    /**
     * Post a message to a booking chat. Without a sender it's a note from BandLink (e.g. "The
     * Doloress accepted"). The other side gets it live over Reverb, and an email if it's still
     * unread after a while.
     */
    public function handle(Conversation $conversation, ?User $sender, string $body): Message
    {
        $message = $conversation->messages()->create([
            'user_id' => $sender?->id,
            'body' => trim($body),
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        if ($sender !== null) {
            // Writing in a chat means you've seen everything before your message.
            $conversation->markReadFor($sender);

            AlertUnreadMessages::dispatch($conversation, $conversation->otherParticipant($sender))
                ->delay(now()->addMinutes(self::ALERT_AFTER_MINUTES));
        }

        MessageSent::dispatch($message->setRelation('conversation', $conversation));

        return $message;
    }
}
