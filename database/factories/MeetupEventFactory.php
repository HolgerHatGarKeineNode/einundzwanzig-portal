<?php

namespace Database\Factories;

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'recurrence_group' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Vorkommen EINER Serie — gemeinsame `recurrence_group`, gemeinsame Regel,
     * aufsteigende Startzeitpunkte im gewaehlten Takt.
     *
     * Gedacht fuer `MeetupEvent::factory()->count(5)->series()`: der Zaehler laeuft
     * ueber die Aufrufe derselben State-Closure, deshalb bekommt jedes Vorkommen sein
     * eigenes Datum statt fuenfmal desselben. Wer zwei getrennte Serien braucht, ruft
     * `series()` zweimal auf — jeder Aufruf zieht ein neues UUID.
     *
     * @param  string|null  $group  Serien-UUID; ohne Angabe wird eine gezogen.
     * @param  CarbonInterface|null  $start  Erstes Vorkommen; Default: naechste Woche.
     * @param  CarbonInterface|null  $endsAt  Ende der SERIE; Default: ein Jahr nach dem Start.
     */
    public function series(
        ?string $group = null,
        RecurrenceType $type = RecurrenceType::Weekly,
        ?CarbonInterface $start = null,
        ?CarbonInterface $endsAt = null,
        int $interval = 1,
    ): static {
        $group ??= (string) Str::uuid();
        $start = ($start ?? now()->addWeek())->copy()->startOfHour();
        $endsAt ??= $start->copy()->addYear();
        $interval = max(1, $interval);
        $occurrence = 0;

        return $this->state(function () use ($group, $type, $start, $endsAt, $interval, &$occurrence): array {
            $step = $interval * $occurrence;
            $occurrence++;

            return [
                'start' => match ($type) {
                    RecurrenceType::Daily => $start->copy()->addDays($step),
                    RecurrenceType::Weekly => $start->copy()->addWeeks($step),
                    RecurrenceType::Yearly => $start->copy()->addYears($step),
                    RecurrenceType::Monthly, RecurrenceType::Custom => $start->copy()->addMonths($step),
                },
                'recurrence_type' => $type->value,
                'recurrence_day_of_week' => null,
                'recurrence_day_position' => null,
                'recurrence_interval' => $interval,
                'recurrence_end_date' => $endsAt,
                'recurrence_group' => $group,
            ];
        });
    }
}
