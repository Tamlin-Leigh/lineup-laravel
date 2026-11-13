<?php

namespace App\Actions;

use App\Enums\LinkKind;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use App\Models\ArtistProfile;
use App\Models\OrganiserProfile;
use App\Models\User;
use App\Models\VenueProfile;
use InvalidArgumentException;

/**
 * Creates or updates the profile (and its links) that matches the user's role.
 * Shared by sign-up and the profile editor; callers wrap it in a transaction.
 */
class SaveProfile
{
    /**
     * @param  array<string, mixed>  $input  Validated ProfileForm sections for the user's role.
     */
    public function handle(User $user, array $input): void
    {
        match ($user->role) {
            UserRole::Venue => $this->saveVenue($user, $input),
            UserRole::Artist => $this->saveArtist($user, $input),
            UserRole::Organiser => $this->saveOrganiser($user, $input),
            UserRole::Admin => throw new InvalidArgumentException('Admin accounts do not have a profile.'),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveVenue(User $user, array $input): void
    {
        $soundEngineer = SoundEngineer::from($input['sound_engineer']);
        $hasOwnEquipment = (bool) $input['has_own_equipment'];

        $profile = $user->venueProfile()->updateOrCreate([], [
            'manager_name' => $input['manager_name'],
            'manager_phone' => $input['manager_phone'],
            'manager_email' => $input['manager_email'],
            'capacity' => (int) $input['capacity'],
            'location' => $input['location'],
            'region' => $input['region'],
            'stage_width' => filled($input['stage_width'] ?? null) ? (float) $input['stage_width'] : null,
            'stage_depth' => filled($input['stage_depth'] ?? null) ? (float) $input['stage_depth'] : null,
            'min_door_charge' => (int) $input['min_door_charge'],
            'sound_engineer' => $soundEngineer,
            'sound_engineer_name' => $soundEngineer === SoundEngineer::InHouse ? $input['sound_engineer_name'] : null,
            'has_own_equipment' => $hasOwnEquipment,
            'equipment_details' => $hasOwnEquipment ? (($input['equipment_details'] ?? '') ?: null) : null,
            'event_days' => array_values(array_unique($input['event_days'])),
        ]);

        $this->syncLinks($profile, LinkKind::Social, $input['social_links'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveArtist(User $user, array $input): void
    {
        $profile = $user->artistProfile()->updateOrCreate([], [
            'manager_name' => $input['manager_name'],
            'manager_phone' => $input['manager_phone'],
            'manager_email' => $input['manager_email'],
            'artist_name' => $input['artist_name'],
            'member_count' => (int) $input['member_count'],
            'member_names' => $input['member_names'],
            'genre' => $input['genre'],
            'region' => $input['region'],
            'bio' => ($input['bio'] ?? '') ?: null,
            'booking_fee' => filled($input['booking_fee'] ?? null) ? (int) $input['booking_fee'] : null,
            'accepts_door_split' => (bool) ($input['accepts_door_split'] ?? false),
        ]);

        $this->syncLinks($profile, LinkKind::Music, $input['music_links'] ?? []);
        $this->syncLinks($profile, LinkKind::Social, $input['social_links'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function saveOrganiser(User $user, array $input): void
    {
        $profile = $user->organiserProfile()->updateOrCreate([], [
            'org_name' => $input['org_name'],
            'contact_number' => $input['contact_number'],
            'contact_email' => $input['contact_email'],
            'region' => $input['region'],
        ]);

        $this->syncLinks($profile, LinkKind::Social, $input['social_links'] ?? []);
    }

    /**
     * Replace the profile's links of one kind with the given rows, keeping their order.
     *
     * @param  list<array{platform: string, url: string}>  $links
     */
    private function syncLinks(ArtistProfile|OrganiserProfile|VenueProfile $profile, LinkKind $kind, array $links): void
    {
        $profile->links()->where('kind', $kind)->delete();

        $profile->links()->createMany(array_map(fn (array $link): array => [
            'kind' => $kind,
            'platform' => $link['platform'],
            'url' => $link['url'],
        ], $links));
    }
}
