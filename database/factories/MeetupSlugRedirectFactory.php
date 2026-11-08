<?php

namespace Database\Factories;

use App\Models\Meetup;
use App\Models\MeetupSlugRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetupSlugRedirect>
 */
class MeetupSlugRedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meetup_id' => Meetup::factory(),
            'slug' => fake()->unique()->slug(3),
        ];
    }
}
