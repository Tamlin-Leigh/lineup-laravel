<?php

namespace App\Livewire\Forms;

use App\Enums\EventDay;
use App\Enums\LinkKind;
use App\Enums\LinkPlatform;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Every role-specific profile field, shared by sign-up and the profile editor so both
 * apply the same rules. Fields are grouped into sections; callers validate only the
 * sections that apply to the role (and, during sign-up, to the current step).
 */
class ProfileForm extends Form
{
    private const PHONE_PATTERN = '/^\+?[0-9]{7,15}$/';

    private const PHONE_FIELDS = ['phone', 'manager_phone', 'contact_number'];

    public const LINK_FIELDS = ['music_links' => LinkKind::Music, 'social_links' => LinkKind::Social];

    public const MAX_LINKS = 10;

    // Personal
    public string $name = '';

    public string $phone = '';

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

    /** Whole rand, or '' for none. */
    public string $booking_fee = '';

    public bool $accepts_door_split = false;

    // Links (band: music + social, venue and organiser: social)
    /** @var list<array{platform: string, url: string}> */
    public array $music_links = [];

    /** @var list<array{platform: string, url: string}> */
    public array $social_links = [];

    // Organiser
    public string $org_name = '';

    public string $contact_number = '';

    public string $contact_email = '';

    /**
     * The sections a role's profile is made of, in sign-up order.
     *
     * @return list<string>
     */
    public static function sectionsFor(?UserRole $role): array
    {
        return match ($role) {
            UserRole::Venue => ['personal', 'manager', 'venue', 'event-days'],
            UserRole::Band => ['personal', 'manager', 'band', 'links'],
            UserRole::Organiser => ['personal', 'organiser'],
            default => ['personal'],
        };
    }

    /**
     * Load the current values from a user and their profile.
     */
    public function fillFromUser(User $user): void
    {
        $this->resetErrorBag();

        $this->name = $user->name;
        $this->phone = (string) $user->phone;

        if ($venue = $user->venueProfile) {
            $this->fill([
                'manager_name' => $venue->manager_name,
                'manager_phone' => $venue->manager_phone,
                'manager_email' => $venue->manager_email,
                'capacity' => (string) $venue->capacity,
                'location' => $venue->location,
                'min_door_charge' => (string) $venue->min_door_charge,
                'sound_engineer' => $venue->sound_engineer->value,
                'sound_engineer_name' => (string) $venue->sound_engineer_name,
                'has_own_equipment' => $venue->has_own_equipment,
                'equipment_details' => (string) $venue->equipment_details,
                'event_days' => $venue->event_days->map(fn (EventDay $day) => $day->value)->values()->all(),
                'social_links' => $this->linkRows($venue->links, LinkKind::Social),
            ]);
        }

        if ($band = $user->bandProfile) {
            $this->fill([
                'manager_name' => $band->manager_name,
                'manager_phone' => $band->manager_phone,
                'manager_email' => $band->manager_email,
                'band_name' => $band->band_name,
                'member_count' => (string) $band->member_count,
                'member_names' => $band->member_names,
                'genre' => $band->genre,
                'bio' => (string) $band->bio,
                'booking_fee' => (string) $band->booking_fee,
                'accepts_door_split' => $band->accepts_door_split,
                'music_links' => $this->linkRows($band->links, LinkKind::Music),
                'social_links' => $this->linkRows($band->links, LinkKind::Social),
            ]);
        }

        if ($organiser = $user->organiserProfile) {
            $this->fill([
                'org_name' => $organiser->org_name,
                'contact_number' => $organiser->contact_number,
                'contact_email' => $organiser->contact_email,
                'social_links' => $this->linkRows($organiser->links, LinkKind::Social),
            ]);
        }
    }

    /**
     * Tidy the input, then validate the given sections together.
     *
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    public function validateSections(array $sections): array
    {
        // Remove formatting people commonly type (spaces, dashes, dots, brackets) but keep anything
        // else, such as letters, so the format check rejects it instead of silently dropping it.
        foreach (self::PHONE_FIELDS as $field) {
            $this->{$field} = preg_replace('/[\s().-]/', '', $this->{$field}) ?? '';
        }

        $rules = array_merge(...array_map(fn (string $section): array => $this->rulesFor($section), $sections));

        return $this->validate($rules);
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesFor(string $section): array
    {
        $phone = ['required', 'string', 'regex:'.self::PHONE_PATTERN];
        // rfc + filter: also rejects addresses without a proper domain, such as "name@gmail".
        $email = ['required', 'string', 'email:rfc,filter', 'max:255'];

        return match ($section) {
            'personal' => [
                'name' => ['required', 'string', 'max:255'],
                'phone' => $phone,
            ],
            'manager' => [
                'manager_name' => ['required', 'string', 'max:255'],
                'manager_phone' => $phone,
                'manager_email' => $email,
            ],
            'venue' => [
                'capacity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'location' => ['required', 'string', 'max:255'],
                'min_door_charge' => ['required', 'integer', 'min:0', 'max:1000000'],
                'sound_engineer' => ['required', Rule::enum(SoundEngineer::class)],
                'sound_engineer_name' => ['nullable', 'required_if:sound_engineer,'.SoundEngineer::InHouse->value, 'string', 'max:255'],
                'has_own_equipment' => ['required', 'boolean'],
                'equipment_details' => ['nullable', 'string', 'max:2000'],
                ...$this->linkRules('social_links'),
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
                'booking_fee' => ['nullable', 'integer', 'min:0', 'max:10000000'],
                'accepts_door_split' => ['boolean'],
            ],
            'links' => [
                ...$this->linkRules('music_links'),
                ...$this->linkRules('social_links'),
            ],
            'organiser' => [
                'org_name' => ['required', 'string', 'max:255'],
                'contact_number' => $phone,
                'contact_email' => $email,
                ...$this->linkRules('social_links'),
            ],
            default => [],
        };
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
     * @param  Collection<int, Link>  $links
     * @return list<array{platform: string, url: string}>
     */
    private function linkRows(Collection $links, LinkKind $kind): array
    {
        return array_values($links
            ->filter(fn (Link $link) => $link->kind === $kind)
            ->map(fn (Link $link) => ['platform' => $link->platform->value, 'url' => $link->url])
            ->all());
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $phone = __('Enter a valid phone number using digits only (7 to 15), optionally starting with +.');

        return [
            'phone.regex' => $phone,
            'manager_phone.regex' => $phone,
            'contact_number.regex' => $phone,
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
            'manager_name' => __("manager's name"),
            'manager_phone' => __("manager's phone number"),
            'manager_email' => __("manager's email"),
            'min_door_charge' => __('minimum door charge'),
            'sound_engineer' => __('sound engineer'),
            'member_count' => __('number of members'),
            'booking_fee' => __('booking fee'),
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
