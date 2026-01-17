<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeetupEvent>
 */
class MeetupEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meetup_id' => \App\Models\Meetup::factory(),
            'start' => now()->addWeek(),
            'location' => fake()->address(),
            'description' => fake()->paragraph(),
            'link' => fake()->url(),
            'attendees' => [],
            'might_attendees' => [],
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
