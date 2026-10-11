<?php

namespace Database\Factories;

use App\Models\BookingRequest;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state: the chat for an accepted booking.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_request_id' => BookingRequest::factory()->accepted(),
            'owner_id' => fn (array $attributes) => BookingRequest::query()->whereKey($attributes['booking_request_id'])->firstOrFail()->event->user_id,
            'recipient_id' => fn (array $attributes) => BookingRequest::query()->whereKey($attributes['booking_request_id'])->firstOrFail()->recipient_id,
        ];
    }
}
