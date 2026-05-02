<?php

namespace Database\Factories;

use App\Models\Lecturer;
use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lecturer>
 */
class LecturerFactory extends Factory
{
    protected $model = Lecturer::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $name = $firstName.' '.$lastName.' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'active' => true,
            'description' => fake()->paragraphs(2, true),
            'subtitle' => 'Bitcoin-Pädagoge & Autor',
            'intro' => fake()->paragraph(),
            'twitter_username' => '@'.fake()->userName(),
            'website' => 'https://'.fake()->domainName(),
            'lightning_address' => NostrHelper::randomLightningAddress($firstName),
            'lnurl' => null,
            'node_id' => null,
            'nostr' => NostrHelper::randomNpub(),
            'paynym' => null,
            'created_by' => User::factory(),
        ];
    }
}
