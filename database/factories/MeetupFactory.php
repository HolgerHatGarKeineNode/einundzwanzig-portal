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
            'is_active' => false,
            'last_event_at' => null,
        ];
    }

    /**
     * Ersteller ohne npub — für Tests rund um das Vereinsmitglied-Gate.
     *
     * {@see Meetup::booted()} hängt created_by automatisch als Leader in die
     * meetup_user-Pivot. Da UserFactory den npub zu 70 % aus der 18 Einträge
     * langen {@see NostrHelper::realNpubs()}-Liste zieht — derselben, aus der
     * die Gate-Tests ihre Vereinsmitglieder nehmen — kollidiert dieser
     * ungewollte Leader gelegentlich mit einem gefakten Mitglied und gatet das
     * Meetup fälschlich. Dieser State nimmt den Zufall aus der Gleichung.
     */
    public function creatorWithoutNostr(): static
    {
        return $this->state(fn (array $attrs) => [
            'created_by' => User::factory()->create(['nostr' => null]),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_active' => true,
            'last_event_at' => now()->subDays(30),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_active' => false,
            'last_event_at' => now()->subYears(2),
        ]);
    }
}
