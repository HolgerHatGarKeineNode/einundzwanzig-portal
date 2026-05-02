<?php

namespace Database\Factories;

use App\Models\BookCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookCase>
 */
class BookCaseFactory extends Factory
{
    protected $model = BookCase::class;

    public function definition(): array
    {
        return [
            'title' => 'Bitcoin Bücherregal '.fake()->unique()->numberBetween(1, 99999),
            'latitude' => fake()->latitude(47.0, 55.0),
            'longitude' => fake()->longitude(5.0, 16.0),
            'address' => fake()->streetAddress().', '.fake()->postcode().' '.fake()->city(),
            'type' => fake()->randomElement(['public', 'private']),
            'open' => fake()->randomElement(['24/7', 'Mo-Fr 09:00-18:00', 'Wochenenden', null]),
            'comment' => fake()->boolean(60) ? fake()->sentence() : null,
            'contact' => fake()->boolean(50) ? fake()->email() : null,
            'bcz' => null,
            'digital' => fake()->boolean(20),
            'icontype' => 'default',
            'deactivated' => false,
            'deactreason' => '',
            'entrytype' => fake()->randomElement(['public', 'private']),
            'homepage' => fake()->boolean(40) ? fake()->url() : null,
            'created_by' => User::factory(),
        ];
    }
}
