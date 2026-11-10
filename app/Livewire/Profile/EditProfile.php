<?php

namespace App\Livewire\Profile;

use App\Actions\UpdateProfile;
use App\Enums\UserRole;
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
 * every section for their role at once. Every role gets the same tabbed layout (the
 * prototype only had tabs for artists), with the tabs that fit its profile.
 */
#[Title('Profile')]
class EditProfile extends Component
{
    /**
     * The tab that shows each field, so a failed save can jump to the first error.
     */
    private const FIELD_TABS = [
        'name' => 'account',
        'phone' => 'account',
        'manager_name' => 'manager',
        'manager_phone' => 'manager',
        'manager_email' => 'manager',
        'capacity' => 'venue',
        'location' => 'venue',
        'min_door_charge' => 'venue',
        'sound_engineer' => 'venue',
        'sound_engineer_name' => 'venue',
        'has_own_equipment' => 'venue',
        'equipment_details' => 'venue',
        'event_days' => 'event-days',
        'artist_name' => 'artist',
        'genre' => 'artist',
        'bio' => 'artist',
        'booking_fee' => 'artist',
        'accepts_door_split' => 'artist',
        'member_count' => 'members',
        'member_names' => 'members',
        'music_links' => 'links',
        'social_links' => 'links',
        'org_name' => 'organiser',
        'contact_number' => 'organiser',
        'contact_email' => 'organiser',
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

        // Admins have no venue / artist / organiser profile; their account details live in settings.
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
        return $this->user->displayName();
    }

    /**
     * Tab key => label for this user's role.
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return ['account' => __('Account')] + match ($this->user->role) {
            UserRole::Venue => [
                'venue' => __('Venue Details'),
                'manager' => __('Manager'),
                'event-days' => __('Event Preferences'),
                'links' => __('Links'),
            ],
            UserRole::Artist => [
                'artist' => __('Artist Profile'),
                'members' => __('Members'),
                'manager' => __('Manager'),
                'links' => __('Links'),
            ],
            UserRole::Organiser => [
                'organiser' => __('Organisation'),
                'links' => __('Links'),
            ],
            UserRole::Admin => [],
        };
    }

    /**
     * The tab to show: the requested one if this role has it, otherwise Account.
     */
    public function activeTab(): string
    {
        return array_key_exists($this->tab, $this->tabs()) ? $this->tab : 'account';
    }

    private function tabFor(string $errorKey): string
    {
        $field = explode('.', str_replace('form.', '', $errorKey))[0];
        $tab = self::FIELD_TABS[$field] ?? 'account';

        return array_key_exists($tab, $this->tabs()) ? $tab : 'account';
    }
}
