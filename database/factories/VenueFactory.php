<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Bitcoin Café', 'Sound Money Lounge', 'Hodl Hodl Bar', 'The Orange Pill', 'Satoshi\'s Place']).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'city_id' => City::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'street' => fake()->streetAddress(),
            'created_by' => User::factory(),
        ];
    }
}
