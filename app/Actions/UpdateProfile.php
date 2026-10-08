<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves the profile editor. Only name and phone change on the user itself: email has its own
 * re-verification flow in settings, and role is never editable.
 */
class UpdateProfile
{
    public function __construct(private SaveProfile $saveProfile) {}

    /**
     * @param  array<string, mixed>  $input  Validated ProfileForm sections for the user's role.
     */
    public function handle(User $user, array $input): void
    {
        DB::transaction(function () use ($user, $input): void {
            $user->update([
                'name' => $input['name'],
                'phone' => $input['phone'],
            ]);

            $this->saveProfile->handle($user, $input);
        });
    }
}
