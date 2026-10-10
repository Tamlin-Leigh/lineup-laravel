<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    /**
     * Define the model's default state: a venue's event waiting on a band's reply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->state(fn () => ['user_id' => User::factory()->venue()]),
            'recipient_id' => User::factory()->band(),
            'status' => BookingStatus::Requested,
            'message' => null,
            'expires_at' => now()->addDays(BookingRequest::REPLY_DAYS),
            'responded_at' => null,
        ];
    }

    /**
     * A band's event asking a venue to host it. States override for(), so set the event or
     * recipient explicitly instead of combining them with this.
     */
    public function toVenue(): static
    {
        return $this->state(fn () => [
            'event_id' => Event::factory()->state(fn () => ['user_id' => User::factory()->band()]),
            'recipient_id' => User::factory()->venue(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => BookingStatus::Accepted, 'responded_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(['status' => BookingStatus::Declined, 'responded_at' => now()]);
    }

    /**
     * Still stored as requested, but its deadline has passed.
     */
    public function overdue(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }
}
