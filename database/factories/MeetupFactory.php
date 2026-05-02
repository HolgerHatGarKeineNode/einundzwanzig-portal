<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Meetup;
use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meetup>
 */
class MeetupFactory extends Factory
{
    protected $model = Meetup::class;

    public function definition(): array
    {
        $cityName = fake()->city();
        $name = 'Bitcoin Meetup '.$cityName;

        return [
            'city_id' => City::factory(),
            'name' => $name.' '.fake()->unique()->numberBetween(1, 99999),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 99999),
            'intro' => fake()->paragraph(),
            'telegram_link' => 'https://t.me/'.Str::slug($cityName).'_btc',
            'webpage' => 'https://'.Str::slug($cityName).'.einundzwanzig.space',
            'twitter_username' => fake()->boolean(40) ? '@btc_'.Str::slug($cityName) : null,
            'github_data' => [],
            'matrix_group' => null,
            'community' => fake()->boolean(80) ? 'einundzwanzig' : null,
            'visible_on_map' => true,
            'simplex' => null,
            'signal' => null,
            'nostr' => NostrHelper::randomNpub(),
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
            'created_by' => User::factory(),
        ];
    }
}
