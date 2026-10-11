<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each person's own channel: new messages in any of their chats (sidebar badge, chat list).
Broadcast::channel('users.{id}', fn (User $user, int $id) => $user->id === $id);

// A booking chat's live messages, only for its two participants.
Broadcast::channel('conversations.{conversation}', fn (User $user, Conversation $conversation) => $user->can('view', $conversation));
