<?php

namespace Database\Factories;

use App\Enums\LinkKind;
use App\Models\BandProfile;
use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = fake()->randomElement(LinkKind::cases());

        return [
            'linkable_type' => 'band_profile',
            'linkable_id' => BandProfile::factory(),
            'kind' => $kind,
            'platform' => fake()->randomElement($kind->platforms()),
            'url' => fake()->url(),
        ];
    }
}
