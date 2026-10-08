<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Profiles are edited by their owner, or by an admin.
     */
    public function update(User $user, User $model): bool
    {
        return $user->is($model) || $user->role === UserRole::Admin;
    }
}
