<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $countries = [
        ['name' => 'Deutschland', 'english_name' => 'Germany', 'code' => 'DE', 'language_codes' => ['de'], 'longitude' => 10.4515, 'latitude' => 51.1657],
        ['name' => 'Österreich', 'english_name' => 'Austria', 'code' => 'AT', 'language_codes' => ['de'], 'longitude' => 14.5501, 'latitude' => 47.5162],
        ['name' => 'Schweiz', 'english_name' => 'Switzerland', 'code' => 'CH', 'language_codes' => ['de', 'fr', 'it'], 'longitude' => 8.2275, 'latitude' => 46.8182],
        ['name' => 'Vereinigte Staaten', 'english_name' => 'United States', 'code' => 'US', 'language_codes' => ['en'], 'longitude' => -95.7129, 'latitude' => 37.0902],
        ['name' => 'Spanien', 'english_name' => 'Spain', 'code' => 'ES', 'language_codes' => ['es'], 'longitude' => -3.7492, 'latitude' => 40.4637],
        ['name' => 'Frankreich', 'english_name' => 'France', 'code' => 'FR', 'language_codes' => ['fr'], 'longitude' => 2.2137, 'latitude' => 46.2276],
        ['name' => 'Vereinigtes Königreich', 'english_name' => 'United Kingdom', 'code' => 'GB', 'language_codes' => ['en'], 'longitude' => -3.4360, 'latitude' => 55.3781],
        ['name' => 'Niederlande', 'english_name' => 'Netherlands', 'code' => 'NL', 'language_codes' => ['nl'], 'longitude' => 5.2913, 'latitude' => 52.1326],
    ];

    public function definition(): array
    {
        $country = self::$countries[$this->faker->unique()->numberBetween(0, count(self::$countries) - 1)];

        return [
            'name' => $country['name'],
            'english_name' => $country['english_name'],
            'code' => $country['code'],
            'language_codes' => $country['language_codes'],
            'longitude' => $country['longitude'],
            'latitude' => $country['latitude'],
        ];
    }
}
