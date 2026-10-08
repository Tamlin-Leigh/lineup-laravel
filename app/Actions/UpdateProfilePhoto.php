<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a new profile photo on the public disk (or clears it) and deletes the file it replaces.
 */
class UpdateProfilePhoto
{
    public function handle(User $user, ?UploadedFile $photo): void
    {
        $previous = $user->profile_photo_path;

        $user->forceFill([
            'profile_photo_path' => $photo?->store('profile-photos', 'public') ?: null,
        ])->save();

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }
    }
}
