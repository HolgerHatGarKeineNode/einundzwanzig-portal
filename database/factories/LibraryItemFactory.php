<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Lecturer;
use App\Models\LibraryItem;
use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryItem>
 */
class LibraryItemFactory extends Factory
{
    protected $model = LibraryItem::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Was ist Bitcoin?',
            'How Lightning Works',
            'Self Custody erklärt',
            'Nostr Protocol Deep Dive',
            'Hyperbitcoinization Thesis',
            'Sound Money Principles',
            'Privacy on Bitcoin',
            'Mining Economics',
        ]).' #'.fake()->unique()->numberBetween(1, 99999);

        return [
            'lecturer_id' => Lecturer::factory(),
            'episode_id' => null,
            'order_column' => fake()->unique()->numberBetween(1, 1_000_000),
            'name' => $name,
            'type' => fake()->randomElement(['article', 'video', 'podcast_episode', 'pdf']),
            'language_code' => fake()->randomElement(['en', 'de']),
            'value' => "# {$name}\n\n".fake()->paragraphs(3, true),
            'subtitle' => fake()->sentence(),
            'excerpt' => fake()->paragraph(),
            'main_image_caption' => null,
            'read_time' => fake()->numberBetween(2, 30).' min',
            'approved' => true,
            'news' => fake()->boolean(20),
            'tweet' => false,
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
            'value_to_be_paid' => null,
            'sats' => fake()->boolean(15) ? fake()->numberBetween(100, 21000) : null,
            'created_by' => User::factory(),
        ];
    }

    public function withEpisode(): static
    {
        return $this->state(fn () => [
            'episode_id' => Episode::factory(),
            'type' => 'podcast_episode',
        ]);
    }
}
