<?php

namespace Database\Factories;

use App\Enums\NostrRsvpStatus;
use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rows as the ingest would have stored them. The hex values are random, not signed:
 * a stored row is past validation, so nothing reading this table re-verifies it.
 *
 * @extends Factory<MeetupEventNostrRsvp>
 */
class MeetupEventNostrRsvpFactory extends Factory
{
    protected $model = MeetupEventNostrRsvp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meetup_event_id' => MeetupEvent::factory(),
            'pubkey' => bin2hex(random_bytes(32)),
            'nostr_event_id' => bin2hex(random_bytes(32)),
            'd_tag' => fake()->uuid(),
            'status' => NostrRsvpStatus::Accepted,
            'rsvp_created_at' => now()->subHour()->timestamp,
            'user_id' => null,
        ];
    }

    public function tentative(): static
    {
        return $this->state(['status' => NostrRsvpStatus::Tentative]);
    }

    public function declined(): static
    {
        return $this->state(['status' => NostrRsvpStatus::Declined]);
    }
}
