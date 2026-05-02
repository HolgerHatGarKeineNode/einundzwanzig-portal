<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lecturer;
use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'lecturer_id' => Lecturer::factory(),
            'name' => fake()->randomElement([
                'Bitcoin Basics',
                'Lightning Network Workshop',
                'Self Custody Mastery',
                'Hardware Wallet Setup',
                'Nostr für Anfänger',
                'Sound Money & Austrian Economics',
                'Bitcoin Mining 101',
                'Privacy & Coinjoin',
            ]).' #'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraphs(2, true),
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
            'created_by' => User::factory(),
        ];
    }
}
