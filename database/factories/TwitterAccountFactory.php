<?php

namespace Database\Factories;

use App\Models\TwitterAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TwitterAccount>
 */
class TwitterAccountFactory extends Factory
{
    protected $model = TwitterAccount::class;

    public function definition(): array
    {
        return [
            'twitter_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'nickname' => fake()->unique()->userName(),
            'token' => Str::random(40),
            'expires_in' => 7200,
            'data' => json_encode(['scope' => ['tweet.read', 'tweet.write', 'users.read']]),
            'refresh_token' => Str::random(40),
        ];
    }
}
