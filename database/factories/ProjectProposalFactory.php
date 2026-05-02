<?php

namespace Database\Factories;

use App\Models\ProjectProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectProposal>
 */
class ProjectProposalFactory extends Factory
{
    protected $model = ProjectProposal::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Bitcoin Beginners Workshop',
            'Self Custody Tour',
            'Nostr Relay Infrastructure',
            'Lightning Educational Content',
            'Open-Source Wallet Translation',
            'Community Hardware Wallet Distribution',
            'Sound Money Documentary',
            'Plebs Education Initiative',
        ]).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'support_in_sats' => fake()->randomElement([21_000, 100_000, 210_000, 1_000_000, 21_000_000]),
            'description' => fake()->paragraphs(3, true),
            'created_by' => User::factory(),
        ];
    }
}
