<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Highscore>
 */
class HighscoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'npub' => 'npub1'.fake()->regexify('[a-z0-9]{20}'),
            'name' => fake()->name(),
            'satoshis' => fake()->numberBetween(0, 100000),
            'blocks' => fake()->numberBetween(0, 1000),
            'achieved_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
