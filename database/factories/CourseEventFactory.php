<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\User;
use App\Models\Venue;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEvent>
 */
class CourseEventFactory extends Factory
{
    protected $model = CourseEvent::class;

    public function definition(): array
    {
        $from = fake()->dateTimeBetween('+1 day', '+3 months');
        $to = (clone $from)->modify('+2 hours');

        return [
            'course_id' => Course::factory(),
            'venue_id' => Venue::factory(),
            'from' => $from,
            'to' => $to,
            'link' => 'https://einundzwanzig.space/courses/'.fake()->slug(),
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
            'created_by' => User::factory(),
        ];
    }
}
