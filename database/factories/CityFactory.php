<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'country_id' => 1,
            'longitude' => fake()->longitude(),
            'latitude' => fake()->latitude(),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
