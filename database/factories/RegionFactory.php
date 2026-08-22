<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->state();

        return [
            'country_id' => Country::factory(),
            'code' => Str::lower($this->faker->unique()->lexify('??')),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }

    /**
     * Indiana, der Bundesstaat aus dem Auslöser-Issue.
     */
    public function indiana(): static
    {
        return $this->state(fn () => [
            'code' => 'in',
            'name' => 'Indiana',
            'slug' => 'indiana',
        ]);
    }
}
