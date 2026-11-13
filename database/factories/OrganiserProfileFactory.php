<?php

namespace Database\Factories;

use App\Enums\Region;
use App\Enums\UserRole;
use App\Models\OrganiserProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganiserProfile>
 */
class OrganiserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Organiser]),
            'org_name' => fake()->company(),
            'contact_number' => fake()->numerify('0#########'),
            'contact_email' => fake()->companyEmail(),
            'region' => Region::Johannesburg,
        ];
    }
}
