<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an account and the profile for its role in one transaction, so a failed profile
 * write never leaves an orphaned login behind (the prototype had to delete the Auth user by hand).
 */
class RegisterUser
{
    public function __construct(private SaveProfile $saveProfile) {}

    /**
     * @param  array<string, mixed>  $input  Validated input from the sign-up flow.
     */
    public function handle(array $input): User
    {
        $user = DB::transaction(function () use ($input): User {
            $user = new User([
                'name' => $input['name'],
                'email' => Str::lower($input['email']),
                'phone' => $input['phone'],
                'password' => $input['password'],
            ]);
            $user->role = UserRole::from($input['role']);
            $user->save();

            $this->saveProfile->handle($user, $input);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
