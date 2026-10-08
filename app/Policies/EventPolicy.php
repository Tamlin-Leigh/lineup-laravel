<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Venues, bands and organisers all run events; admins only oversee them.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, UserRole::registrable(), true);
    }

    /**
     * Published events are public; drafts and cancelled events only to their owner (and admins).
     */
    public function view(User $user, Event $event): bool
    {
        return $event->status === EventStatus::Published || $this->update($user, $event);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id || $user->role === UserRole::Admin;
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
