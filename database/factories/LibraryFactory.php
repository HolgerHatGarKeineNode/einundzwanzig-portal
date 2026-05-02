<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Library>
 */
class LibraryFactory extends Factory
{
    protected $model = Library::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Bitcoin Standard Library',
                'Lightning Network Resources',
                'Nostr Knowledge Base',
                'Self Custody Guides',
                'Sound Money Classics',
                'Cypherpunk Archive',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            'is_public' => true,
            'language_codes' => ['en', 'de'],
            'parent_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
