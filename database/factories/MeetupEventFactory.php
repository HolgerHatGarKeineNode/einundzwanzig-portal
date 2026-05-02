<?php

namespace Database\Factories;

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetupEvent>
 */
class MeetupEventFactory extends Factory
{
    protected $model = MeetupEvent::class;

    public function definition(): array
    {
        return [
            'meetup_id' => Meetup::factory(),
            'start' => now()->addDays(fake()->numberBetween(1, 60)),
            'location' => fake()->address(),
            'description' => fake()->paragraph(),
            'link' => fake()->url(),
            'attendees' => [],
            'might_attendees' => [],
            'nostr_status' => NostrHelper::fakeNostrEventStatus(),
            'recurrence_type' => fake()->boolean(40) ? RecurrenceType::Monthly : null,
            'recurrence_day_of_week' => null,
            'recurrence_day_position' => null,
            'recurrence_interval' => 1,
            'recurrence_end_date' => null,
            'created_by' => User::factory(),
        ];
    }
}
