<?php

namespace App\Livewire\Profile;

use App\Actions\UpdateProfile;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithProfileForm;
use App\Livewire\Forms\ProfileForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The signed-in user's profile: read-only by default, with an edit mode that saves
 * every section for their role at once. Bands get tabs, as in the prototype.
 */
#[Title('Profile')]
class EditProfile extends Component
{
    use InteractsWithProfileForm;

    public const BAND_TABS = [
        'account' => 'Account',
        'band' => 'Band Profile',
        'members' => 'Members',
        'manager' => 'Manager',
        'links' => 'Links',
    ];

    /**
     * The band tab that shows each field, so a failed save can jump to the first error.
     */
    private const FIELD_TABS = [
        'name' => 'account',
        'phone' => 'account',
        'band_name' => 'band',
        'genre' => 'band',
        'bio' => 'band',
        'member_count' => 'members',
        'member_names' => 'members',
        'manager_name' => 'manager',
        'manager_phone' => 'manager',
        'manager_email' => 'manager',
        'music_links' => 'links',
        'social_links' => 'links',
    ];

    #[Locked]
    public User $user;

    public ProfileForm $form;

    public bool $editing = false;

    public string $tab = 'account';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->user = $user;

        // Admins have no venue / band / organiser profile; their account details live in settings.
        if ($user->role === UserRole::Admin) {
            $this->redirectRoute('profile.edit', navigate: true);

            return;
        }

        $this->form->fillFromUser($user);
    }

    public function edit(): void
    {
        $this->authorize('update', $this->user);

        $this->form->fillFromUser($this->user);
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->form->fillFromUser($this->user);
        $this->editing = false;
    }

    public function save(UpdateProfile $updateProfile): void
    {
        $this->authorize('update', $this->user);

        try {
            $validated = $this->form->validateSections(ProfileForm::sectionsFor($this->user->role));
        } catch (ValidationException $e) {
            $this->tab = $this->tabFor((string) array_key_first($e->errors()));

            throw $e;
        }

        $updateProfile->handle($this->user, $validated);

        $this->user->refresh();
        $this->form->fillFromUser($this->user);
        $this->editing = false;

        Flux::toast(variant: 'success', text: __('Profile saved.'));
    }

    /**
     * The heading shown next to the avatar: the act's or organisation's name where there is one.
     */
    public function displayName(): string
    {
        return match ($this->user->role) {
            UserRole::Band => $this->user->bandProfile->band_name ?? $this->user->name,
            UserRole::Organiser => $this->user->organiserProfile->org_name ?? $this->user->name,
            default => $this->user->name,
        };
    }

    private function tabFor(string $errorKey): string
    {
        $field = explode('.', str_replace('form.', '', $errorKey))[0];

        return self::FIELD_TABS[$field] ?? 'account';
    }
}
