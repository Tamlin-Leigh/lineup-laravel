<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\BandProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BandProfile>
 */
class BandProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $memberCount = fake()->numberBetween(1, 6);

        return [
            'user_id' => User::factory()->state(['role' => UserRole::Band]),
            'manager_name' => fake()->name(),
            'manager_phone' => fake()->numerify('0#########'),
            'manager_email' => fake()->safeEmail(),
            'band_name' => 'The '.ucfirst(fake()->word()).'s',
            'member_count' => $memberCount,
            'member_names' => implode(', ', array_map(fn () => fake()->firstName(), range(1, $memberCount))),
            'genre' => fake()->randomElement(['Rock', 'Jazz', 'Hip-Hop', 'Indie', 'Electronic', 'Folk']),
            'bio' => fake()->optional()->sentence(12),
            'booking_fee' => fake()->optional()->randomElement([1500, 2500, 4000, 6000]),
            'accepts_door_split' => fake()->boolean(),
        ];
    }
}
