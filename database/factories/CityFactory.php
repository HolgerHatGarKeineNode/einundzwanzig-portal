<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @var array<string, array<int, array{name: string, lat: float, lon: float}>>
     */
    private static array $citiesByCode = [
        'DE' => [
            ['name' => 'Berlin', 'lat' => 52.5200, 'lon' => 13.4050],
            ['name' => 'München', 'lat' => 48.1351, 'lon' => 11.5820],
            ['name' => 'Hamburg', 'lat' => 53.5511, 'lon' => 9.9937],
            ['name' => 'Köln', 'lat' => 50.9375, 'lon' => 6.9603],
            ['name' => 'Frankfurt', 'lat' => 50.1109, 'lon' => 8.6821],
            ['name' => 'Leipzig', 'lat' => 51.3397, 'lon' => 12.3731],
            ['name' => 'Dresden', 'lat' => 51.0504, 'lon' => 13.7373],
            ['name' => 'Stuttgart', 'lat' => 48.7758, 'lon' => 9.1829],
        ],
        'AT' => [
            ['name' => 'Wien', 'lat' => 48.2082, 'lon' => 16.3738],
            ['name' => 'Graz', 'lat' => 47.0707, 'lon' => 15.4395],
            ['name' => 'Innsbruck', 'lat' => 47.2692, 'lon' => 11.4041],
            ['name' => 'Salzburg', 'lat' => 47.8095, 'lon' => 13.0550],
        ],
        'CH' => [
            ['name' => 'Zürich', 'lat' => 47.3769, 'lon' => 8.5417],
            ['name' => 'Bern', 'lat' => 46.9480, 'lon' => 7.4474],
            ['name' => 'Genf', 'lat' => 46.2044, 'lon' => 6.1432],
        ],
        'US' => [
            ['name' => 'New York', 'lat' => 40.7128, 'lon' => -74.0060],
            ['name' => 'Austin', 'lat' => 30.2672, 'lon' => -97.7431],
            ['name' => 'Miami', 'lat' => 25.7617, 'lon' => -80.1918],
        ],
    ];

    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'country_id' => Country::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 99999),
            'longitude' => fake()->longitude(),
            'latitude' => fake()->latitude(),
            'osm_relation' => null,
            'simplified_geojson' => null,
            'population' => fake()->numberBetween(10000, 5000000),
            'population_date' => fake()->date(),
            'created_by' => User::factory(),
        ];
    }

    public function inGermany(): static
    {
        $city = collect(self::$citiesByCode['DE'])->random();

        return $this->state(fn (array $attrs) => [
            'name' => $city['name'],
            'slug' => Str::slug($city['name']).'-de-'.fake()->unique()->numberBetween(1000, 99999),
            'latitude' => $city['lat'],
            'longitude' => $city['lon'],
        ]);
    }
}
