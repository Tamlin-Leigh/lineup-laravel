<?php

namespace App\Actions;

use App\Enums\LinkKind;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use App\Models\BandProfile;
use App\Models\OrganiserProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates an account and the profile for its role in one transaction, so a failed profile
 * write never leaves an orphaned login behind (the prototype had to delete the Auth user by hand).
 */
class RegisterUser
{
    /**
     * @param  array<string, mixed>  $input  Validated input from the sign-up flow.
     */
    public function handle(array $input): User
    {
        $role = UserRole::from($input['role']);

        $user = DB::transaction(function () use ($input, $role): User {
            $user = new User([
                'name' => $input['name'],
                'email' => Str::lower($input['email']),
                'phone' => $input['phone'],
                'password' => $input['password'],
            ]);
            $user->role = $role;
            $user->save();

            match ($role) {
                UserRole::Venue => $this->createVenueProfile($user, $input),
                UserRole::Band => $this->createBandProfile($user, $input),
                UserRole::Organiser => $this->createOrganiserProfile($user, $input),
                UserRole::Admin => throw new InvalidArgumentException('The admin role cannot be chosen at sign-up.'),
            };

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createVenueProfile(User $user, array $input): void
    {
        $soundEngineer = SoundEngineer::from($input['sound_engineer']);
        $hasOwnEquipment = (bool) $input['has_own_equipment'];

        $user->venueProfile()->create([
            'manager_name' => $input['manager_name'],
            'manager_phone' => $input['manager_phone'],
            'manager_email' => $input['manager_email'],
            'capacity' => (int) $input['capacity'],
            'location' => $input['location'],
            'min_door_charge' => (int) $input['min_door_charge'],
            'sound_engineer' => $soundEngineer,
            'sound_engineer_name' => $soundEngineer === SoundEngineer::InHouse ? $input['sound_engineer_name'] : null,
            'has_own_equipment' => $hasOwnEquipment,
            'equipment_details' => $hasOwnEquipment ? ($input['equipment_details'] ?: null) : null,
            'event_days' => array_values(array_unique($input['event_days'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createBandProfile(User $user, array $input): void
    {
        $profile = $user->bandProfile()->create([
            'manager_name' => $input['manager_name'],
            'manager_phone' => $input['manager_phone'],
            'manager_email' => $input['manager_email'],
            'band_name' => $input['band_name'],
            'member_count' => (int) $input['member_count'],
            'member_names' => $input['member_names'],
            'genre' => $input['genre'],
            'bio' => ($input['bio'] ?? '') ?: null,
        ]);

        $this->createLinks($profile, LinkKind::Music, $input['music_links'] ?? []);
        $this->createLinks($profile, LinkKind::Social, $input['social_links'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createOrganiserProfile(User $user, array $input): void
    {
        $profile = $user->organiserProfile()->create([
            'org_name' => $input['org_name'],
            'contact_number' => $input['contact_number'],
            'contact_email' => $input['contact_email'],
        ]);

        $this->createLinks($profile, LinkKind::Social, $input['social_links'] ?? []);
    }

    /**
     * @param  list<array{platform: string, url: string}>  $links
     */
    private function createLinks(BandProfile|OrganiserProfile $profile, LinkKind $kind, array $links): void
    {
        $profile->links()->createMany(array_map(fn (array $link): array => [
            'kind' => $kind,
            'platform' => $link['platform'],
            'url' => $link['url'],
        ], $links));
    }
}
