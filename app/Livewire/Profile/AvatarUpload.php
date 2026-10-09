<?php

namespace App\Livewire\Profile;

use App\Actions\UpdateProfilePhoto;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Click the avatar to pick a photo; it is saved as soon as the upload finishes.
 */
class AvatarUpload extends Component
{
    use WithFileUploads;

    #[Locked]
    public User $user;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public function updatedPhoto(UpdateProfilePhoto $updateProfilePhoto): void
    {
        $this->authorize('update', $this->user);

        $this->validate(
            ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']],
            ['photo.max' => __('The photo must be smaller than 5 MB.')],
        );

        $updateProfilePhoto->handle($this->user, $this->photo);
        $this->reset('photo');

        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    public function removePhoto(UpdateProfilePhoto $updateProfilePhoto): void
    {
        $this->authorize('update', $this->user);

        $updateProfilePhoto->handle($this->user, null);

        Flux::toast(text: __('Profile photo removed.'));
    }
}
