<?php

namespace Database\Factories;

use App\Models\ProjectProposal;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_proposal_id' => ProjectProposal::factory(),
            'value' => fake()->randomElement([0, 1]),
            'reason' => fake()->boolean(40) ? fake()->sentence() : null,
            'created_by' => User::factory(),
        ];
    }
}
