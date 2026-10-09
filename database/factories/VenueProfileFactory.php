<?php

namespace Database\Factories;

use App\Enums\EventDay;
use App\Enums\SoundEngineer;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VenueProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenueProfile>
 */
class VenueProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $soundEngineer = fake()->randomElement(SoundEngineer::cases());
        $hasOwnEquipment = fake()->boolean();

        return [
            'user_id' => User::factory()->state(['role' => UserRole::Venue]),
            'manager_name' => fake()->name(),
            'manager_phone' => fake()->numerify('0#########'),
            'manager_email' => fake()->safeEmail(),
            'capacity' => fake()->numberBetween(50, 2000),
            'location' => fake()->address(),
            'min_door_charge' => fake()->numberBetween(0, 300),
            'sound_engineer' => $soundEngineer,
            'sound_engineer_name' => $soundEngineer === SoundEngineer::InHouse ? fake()->name() : null,
            'has_own_equipment' => $hasOwnEquipment,
            'equipment_details' => $hasOwnEquipment ? 'PA system, drum kit, two guitar amps' : null,
            'event_days' => fake()->randomElements(EventDay::cases(), fake()->numberBetween(1, 2)),
        ];
    }
}
