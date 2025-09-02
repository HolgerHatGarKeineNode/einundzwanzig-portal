<?php

namespace Database\Factories;

use App\Models\ProjectProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'support_in_sats' => $this->faker->randomNumber(),
            'description' => $this->faker->text(),
        ];
    }
}
