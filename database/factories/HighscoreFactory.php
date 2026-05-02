<?php

namespace Database\Factories;

use App\Models\Highscore;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Highscore>
 */
class HighscoreFactory extends Factory
{
    protected $model = Highscore::class;

    public function definition(): array
    {
        return [
            'npub' => NostrHelper::randomNpub(),
            'name' => fake()->name(),
            'satoshis' => fake()->numberBetween(0, 100000),
            'blocks' => fake()->numberBetween(0, 1000),
            'achieved_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
