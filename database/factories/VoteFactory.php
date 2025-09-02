<?php

namespace Database\Factories;

use App\Models\ProjectProposal;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Vote::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_proposal_id' => ProjectProposal::factory(),
            'value' => $this->faker->randomNumber(),
            'reason' => $this->faker->text,
        ];
    }
}
