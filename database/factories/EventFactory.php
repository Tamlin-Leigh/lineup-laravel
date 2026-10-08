<?php

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state: a published event in the coming weeks.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->band(),
            'venue_profile_id' => null,
            'title' => ucwords(rtrim(fake()->sentence(3), '.')),
            'description' => fake()->sentence(12),
            'category' => fake()->randomElement(EventCategory::cases()),
            'location' => fake()->city(),
            'starts_at' => now()->addDays(fake()->numberBetween(1, 60))->setTime(fake()->numberBetween(17, 22), 0),
            'price' => fake()->randomElement([0, 60, 90, 120, 150, 180]),
            'status' => EventStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => EventStatus::Draft]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => EventStatus::Cancelled]);
    }

    public function past(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subDays(fake()->numberBetween(1, 120))->setTime(20, 0)]);
    }
}
