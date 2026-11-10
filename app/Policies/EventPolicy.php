<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Venues, artists and organisers all run events; admins only oversee them.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, UserRole::registrable(), true);
    }

    /**
     * Published events are public; drafts and cancelled events only to their owner (and admins), and
     * to the artists and venues asked to take part, so they can see what they're agreeing to.
     */
    public function view(User $user, Event $event): bool
    {
        return $event->status === EventStatus::Published
            || $this->update($user, $event)
            || $event->bookingRequests()->where('recipient_id', $user->id)->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->id === $event->user_id || $user->role === UserRole::Admin;
    }

    /**
     * Who's been invited is only for the people organising the event and those invited to it, even
     * once the event itself is public.
     */
    public function viewInvitedArtists(User $user, Event $event): bool
    {
        return $this->update($user, $event)
            || $event->bookingRequests()->where('recipient_id', $user->id)->exists();
    }

    /**
     * Owners can send booking requests for their own events while they are still to come.
     */
    public function sendBookingRequests(User $user, Event $event): bool
    {
        return $user->id === $event->user_id
            && $user->bookableRoles() !== []
            && $event->status !== EventStatus::Cancelled
            && ! $event->isPast();
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
