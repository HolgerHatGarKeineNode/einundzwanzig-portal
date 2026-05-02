<?php

namespace Database\Factories;

use App\Models\BookCase;
use App\Models\OrangePill;
use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrangePill>
 */
class OrangePillFactory extends Factory
{
    protected $model = OrangePill::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_case_id' => BookCase::factory(),
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'amount' => fake()->numberBetween(1, 21),
            'comment' => fake()->boolean(60) ? fake()->sentence() : null,
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
        ];
    }
}
