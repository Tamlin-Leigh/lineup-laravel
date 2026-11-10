<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Public profiles: any signed-in user may view a verified venue, artist or organiser.
     * Admins have no public profile.
     */
    public function view(User $user, User $model): bool
    {
        return in_array($model->role, UserRole::registrable(), true)
            && ($model->hasVerifiedEmail() || $user->is($model));
    }

    /**
     * Profiles are edited by their owner, or by an admin.
     */
    public function update(User $user, User $model): bool
    {
        return $user->is($model) || $user->role === UserRole::Admin;
    }
}
