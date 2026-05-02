<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    protected $model = Episode::class;

    public function definition(): array
    {
        return [
            'guid' => (string) Str::uuid(),
            'podcast_id' => Podcast::factory(),
            'data' => json_encode([
                'title' => 'Folge '.fake()->numberBetween(1, 999).': '.fake()->sentence(4),
                'description' => fake()->paragraph(),
                'pubDate' => fake()->dateTimeBetween('-1 year', 'now')->format(DATE_RFC2822),
                'duration' => fake()->numberBetween(900, 7200),
                'enclosure' => [
                    'url' => 'https://media.einundzwanzig.space/'.Str::random(16).'.mp3',
                    'type' => 'audio/mpeg',
                ],
            ]),
            'created_by' => User::factory(),
        ];
    }
}
