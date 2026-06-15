<?php

namespace App\Actions\MeetupEvents;

use App\Enums\RecurrenceType;
use App\Models\MeetupEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Persists a recurrence rule as concrete individual MeetupEvent records,
 * mirroring the Livewire editor: each occurrence is stored as a standalone
 * event without recurrence metadata.
 */
class CreateMeetupEventSeries
{
    public function __construct(private ExpandRecurrenceSeries $expandRecurrenceSeries) {}

    /**
     * @param  array<string, mixed>  $data  Validated StoreMeetupEventRequest payload.
     * @return Collection<int, MeetupEvent>
     */
    public function handle(array $data): Collection
    {
        $dates = $this->expandRecurrenceSeries->handle(
            Carbon::parse($data['start']),
            Carbon::parse($data['recurrence_end_date']),
            RecurrenceType::from($data['recurrence_type']),
            $data['recurrence_day_of_week'] ?? null,
            $data['recurrence_day_position'] ?? null,
        );

        return collect($dates)->map(fn (Carbon $start): MeetupEvent => MeetupEvent::create([
            'meetup_id' => $data['meetup_id'],
            'start' => $start,
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'link' => $data['link'] ?? null,
            'attendees' => [],
            'might_attendees' => [],
        ]));
    }
}
