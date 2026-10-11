<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * A booking chat is private to the event's owner and the band or venue that accepted.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Participants can write while the booking and event are still on.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation) && ! $conversation->isReadOnly();
    }
}
