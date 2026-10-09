<?php

namespace App\Livewire\Auth;

use App\Actions\RegisterUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\EventDay;
use App\Enums\LinkKind;
use App\Enums\LinkPlatform;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Multi-step sign-up. Steps 0-1 are shared; the rest branch on the chosen role.
 * Each step is validated on the server before moving on, and every step for the
 * role is validated again at the end, so skipping ahead never bypasses a rule.
 */
#[Layout('layouts::auth.brand')]
#[Title('Create account')]
class Register extends Component
{
    use PasswordValidationRules, ProfileValidationRules;

    private const PHONE_PATTERN = '/^\+?[0-9]{7,15}$/';

    private const PHONE_FIELDS = ['phone', 'manager_phone', 'contact_number'];

    private const LINK_FIELDS = ['music_links' => LinkKind::Music, 'social_links' => LinkKind::Social];

    private const MAX_LINKS = 10;

    #[Locked]
    public int $step = 0;

    // Account
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Personal info
    public string $name = '';

    public string $phone = '';

    public string $role = '';

    // Manager (venue + band)
    public string $manager_name = '';

    public string $manager_phone = '';

    public string $manager_email = '';

    // Venue
    public string $capacity = '';

    public string $location = '';

    public string $min_door_charge = '';

    public string $sound_engineer = '';

    public string $sound_engineer_name = '';

    public ?bool $has_own_equipment = null;

    public string $equipment_details = '';

    /** @var list<string> */
    public array $event_days = [];

    // Band
    public string $band_name = '';

    public string $member_count = '';

    public string $member_names = '';

    public string $genre = '';

    public string $bio = '';

    // Links (band: music + social, organiser: social)
    /** @var list<array{platform: string, url: string}> */
    public array $music_links = [];

    /** @var list<array{platform: string, url: string}> */
    public array $social_links = [];

    // Organiser
    public string $org_name = '';

    public string $contact_number = '';

    public string $contact_email = '';

    /**
     * Step key => label for the chosen role.
     *
     * @return array<string, string>
     */
    public function steps(): array
    {
        $shared = ['account' => __('Account Details'), 'personal' => __('Personal Info')];

        return $shared + match (UserRole::tryFrom($this->role)) {
            UserRole::Venue => [
                'manager' => __('Manager Details'),
                'venue' => __('Venue Details'),
                'event-days' => __('Event Preferences'),
            ],
            UserRole::Band => [
                'manager' => __('Manager Details'),
                'band' => __('Band Details'),
                'links' => __('Links'),
            ],
            UserRole::Organiser => ['organiser' => __('Organiser Details')],
            default => [],
        };
    }

    public function currentStep(): string
    {
        return array_keys($this->steps())[$this->step] ?? 'account';
    }

    public function isLastStep(): bool
    {
        return $this->role !== '' && $this->step === count($this->steps()) - 1;
    }

    /**
     * Validate the current step, then advance (or create the account on the last step).
     */
    public function next(RegisterUser $registerUser): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate($this->rulesFor($this->currentStep()));

        if (! $this->isLastStep()) {
            $this->step++;

            return;
        }

        $validated = $this->validate($this->rulesForAllSteps());

        $user = $registerUser->handle($validated);

        Auth::login($user);
        Session::regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function back(): void
    {
        $this->resetErrorBag();

        if ($this->step === 0) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->step--;
    }

    public function toggleEventDay(string $day): void
    {
        if (EventDay::tryFrom($day) === null) {
            return;
        }

        $this->event_days = in_array($day, $this->event_days, true)
            ? array_values(array_diff($this->event_days, [$day]))
            : [...$this->event_days, $day];
    }

    public function addLink(string $field): void
    {
        $kind = self::LINK_FIELDS[$field] ?? null;

        if ($kind === null || count($this->{$field}) >= self::MAX_LINKS) {
            return;
        }

        $this->{$field}[] = ['platform' => $kind->platforms()[0]->value, 'url' => ''];
    }

    public function removeLink(string $field, int $index): void
    {
        if (! array_key_exists($field, self::LINK_FIELDS)) {
            return;
        }

        $links = $this->{$field};
        unset($links[$index]);
        $this->{$field} = array_values($links);

        $this->resetErrorBag();
    }

    /**
     * Keep phone fields to digits plus an optional leading "+", as the prototype did.
     */
    public function updated(string $property): void
    {
        if (in_array($property, self::PHONE_FIELDS, true)) {
            $value = trim($this->{$property});
            $digits = preg_replace('/\D/', '', $value) ?? '';

            $this->{$property} = (str_starts_with($value, '+') ? '+' : '').$digits;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(string $step): array
    {
        $phone = ['required', 'string', 'regex:'.self::PHONE_PATTERN];
        $contactEmail = ['required', 'string', 'email', 'max:255'];

        return match ($step) {
            'account' => [
                'email' => $this->emailRules(),
                'password' => $this->passwordRules(),
            ],
            'personal' => [
                'name' => $this->nameRules(),
                'phone' => $phone,
                'role' => ['required', Rule::enum(UserRole::class)->only(UserRole::registrable())],
            ],
            'manager' => [
                'manager_name' => ['required', 'string', 'max:255'],
                'manager_phone' => $phone,
                'manager_email' => $contactEmail,
            ],
            'venue' => [
                'capacity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'location' => ['required', 'string', 'max:255'],
                'min_door_charge' => ['required', 'integer', 'min:0', 'max:1000000'],
                'sound_engineer' => ['required', Rule::enum(SoundEngineer::class)],
                'sound_engineer_name' => ['nullable', 'required_if:sound_engineer,'.SoundEngineer::InHouse->value, 'string', 'max:255'],
                'has_own_equipment' => ['required', 'boolean'],
                'equipment_details' => ['nullable', 'string', 'max:2000'],
            ],
            'event-days' => [
                'event_days' => ['required', 'array', 'min:1'],
                'event_days.*' => ['distinct', Rule::enum(EventDay::class)],
            ],
            'band' => [
                'band_name' => ['required', 'string', 'max:255'],
                'member_count' => ['required', 'integer', 'min:1', 'max:100'],
                'member_names' => ['required', 'string', 'max:1000'],
                'genre' => ['required', 'string', 'max:255'],
                'bio' => ['nullable', 'string', 'max:2000'],
            ],
            'links' => [
                ...$this->linkRules('music_links'),
                ...$this->linkRules('social_links'),
            ],
            'organiser' => [
                'org_name' => ['required', 'string', 'max:255'],
                'contact_number' => $phone,
                'contact_email' => $contactEmail,
                ...$this->linkRules('social_links'),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForAllSteps(): array
    {
        return array_merge(...array_map(
            fn (string $step): array => $this->rulesFor($step),
            array_keys($this->steps()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function linkRules(string $field): array
    {
        return [
            $field => ['array', 'max:'.self::MAX_LINKS],
            "{$field}.*.platform" => ['required', Rule::enum(LinkPlatform::class)->only(self::LINK_FIELDS[$field]->platforms())],
            "{$field}.*.url" => ['required', 'string', 'url:http,https', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $phone = __('Enter a valid phone number: 7 to 15 digits, optionally starting with +.');

        return [
            'phone.regex' => $phone,
            'manager_phone.regex' => $phone,
            'contact_number.regex' => $phone,
            'role.required' => __('Choose the type of account you need.'),
            'sound_engineer_name.required_if' => __("Enter the sound engineer's name."),
            'has_own_equipment.required' => __('Tell us whether you have your own equipment.'),
            'event_days.required' => __('Pick at least one option.'),
            'event_days.min' => __('Pick at least one option.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'phone' => __('phone number'),
            'role' => __('account type'),
            'manager_name' => __("manager's name"),
            'manager_phone' => __("manager's phone number"),
            'manager_email' => __("manager's email"),
            'min_door_charge' => __('minimum door charge'),
            'sound_engineer' => __('sound engineer'),
            'member_count' => __('number of members'),
            'org_name' => __('organisation name'),
            'music_links' => __('music links'),
            'music_links.*.platform' => __('platform'),
            'music_links.*.url' => __('link'),
            'social_links' => __('social links'),
            'social_links.*.platform' => __('platform'),
            'social_links.*.url' => __('link'),
        ];
    }
}
