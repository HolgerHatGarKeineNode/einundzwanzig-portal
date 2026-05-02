<?php

namespace Database\Factories;

use App\Models\Podcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Podcast>
 */
class PodcastFactory extends Factory
{
    protected $model = Podcast::class;

    public function definition(): array
    {
        $name = 'Einundzwanzig Podcast '.fake()->unique()->numberBetween(1, 9999);

        return [
            'guid' => (string) Str::uuid(),
            'locked' => true,
            'title' => $name,
            'link' => 'https://einundzwanzig.space/podcast/'.Str::slug($name),
            'language_code' => 'de',
            'data' => json_encode([
                'description' => fake()->paragraph(),
                'author' => 'Einundzwanzig',
                'image' => null,
            ]),
            'created_by' => User::factory(),
        ];
    }
}
