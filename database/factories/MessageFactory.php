<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state: the event's owner writing in the chat.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => fn (array $attributes) => Conversation::query()->whereKey($attributes['conversation_id'])->firstOrFail()->owner_id,
            'body' => fake()->sentence(),
        ];
    }
}
