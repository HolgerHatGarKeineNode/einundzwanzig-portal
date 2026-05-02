<?php

namespace Database\Factories;

use App\Models\BitcoinEvent;
use App\Models\User;
use App\Models\Venue;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BitcoinEvent>
 */
class BitcoinEventFactory extends Factory
{
    protected $model = BitcoinEvent::class;

    public function definition(): array
    {
        $from = fake()->dateTimeBetween('+1 week', '+6 months');
        $to = (clone $from)->modify('+1 day');

        return [
            'venue_id' => Venue::factory(),
            'from' => $from,
            'to' => $to,
            'title' => fake()->randomElement([
                'Bitcoin Conference',
                'Lightning Summit',
                'Nostrasia',
                'Bitcoin Park Meetup',
                'Sound Money Symposium',
                'Plan B Forum',
            ]).' '.$from->format('Y'),
            'description' => fake()->paragraphs(2, true),
            'link' => fake()->url(),
            'show_worldwide' => fake()->boolean(15),
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
            'created_by' => User::factory(),
        ];
    }
}
