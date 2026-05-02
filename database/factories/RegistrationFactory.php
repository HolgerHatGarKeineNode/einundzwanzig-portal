<?php

namespace Database\Factories;

use App\Models\CourseEvent;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'course_event_id' => CourseEvent::factory(),
            'participant_id' => Participant::factory(),
            'active' => true,
        ];
    }
}
