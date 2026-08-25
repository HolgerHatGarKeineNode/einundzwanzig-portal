<?php

namespace App\Actions\MeetupEvents;

use App\Enums\RecurrenceType;
use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;
use App\Observers\MeetupEventObserver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Persists a recurrence rule as concrete individual MeetupEvent records.
 *
 * Every occurrence is a standalone row — that is the shape
 * {@see MeetupEventResource} publishes, and each date carries its
 * own RSVP lists. What the occurrences share is written onto every one of them:
 *
 *  - the five `recurrence_*` values, so the rule that produced the date is readable
 *    from the date itself (the resource has promised these fields all along and
 *    returned null for every series ever created);
 *  - one `recurrence_group` UUID, the series identity. No parent pointer: the first
 *    occurrence is an ordinary event and may be deleted.
 *
 * The whole run sits inside {@see MeetupEventObserver::batched()} so the meetup's
 * activity is recalculated once, not once per occurrence.
 */
class CreateMeetupEventSeries
{
    /**
     * The columns every occurrence copies verbatim from the request.
     *
     * Kept in step with the Livewire editor
     * (`resources/views/livewire/meetups/create-edit-events.blade.php`), which writes
     * exactly these. Until P5 the API wrote only four of them, so the same rule created
     * different rows depending on which door it came through.
     */
    private const COPIED_COLUMNS = [
        'title',
        'location',
        'description',
        'link',
        'osm_type',
        'osm_id',
        'osm_name',
        'osm_address',
        'osm_lat',
        'osm_lon',
    ];

    public function __construct(private ExpandRecurrenceSeries $expandRecurrenceSeries) {}

    /**
     * @param  array<string, mixed>  $data  Validated StoreMeetupEventRequest payload.
     * @return Collection<int, MeetupEvent>
     */
    public function handle(array $data): Collection
    {
        $start = Carbon::parse($data['start']);

        $dates = $this->expandRecurrenceSeries->handle(
            $start,
            Carbon::parse($data['recurrence_end_date']),
            RecurrenceType::from($data['recurrence_type']),
            $data['recurrence_day_of_week'] ?? null,
            $data['recurrence_day_position'] ?? null,
            isset($data['recurrence_interval']) ? (int) $data['recurrence_interval'] : null,
        );

        /*
         * `end` in the payload is the end of the FIRST occurrence, an absolute
         * timestamp. Every further occurrence gets the same duration, not the same
         * timestamp — otherwise all fifty dates would end on the day the first one did.
         */
        $duration = isset($data['end']) && $data['end'] !== null
            ? (int) $start->diffInSeconds(Carbon::parse($data['end']))
            : null;

        $shared = [
            ...collect(self::COPIED_COLUMNS)
                ->mapWithKeys(fn (string $column): array => [$column => $data[$column] ?? null])
                ->all(),
            'meetup_id' => $data['meetup_id'],
            'recurrence_type' => $data['recurrence_type'],
            'recurrence_day_of_week' => $data['recurrence_day_of_week'] ?? null,
            'recurrence_day_position' => $data['recurrence_day_position'] ?? null,
            'recurrence_interval' => isset($data['recurrence_interval']) ? max(1, (int) $data['recurrence_interval']) : 1,
            'recurrence_end_date' => $data['recurrence_end_date'],
            'recurrence_group' => (string) Str::uuid(),
            'attendees' => [],
            'might_attendees' => [],
        ];

        // created_by is filled by SetsCreatedBy from the authenticated user; an explicit
        // value in the payload (there is none in the request today) still wins.
        if (isset($data['created_by'])) {
            $shared['created_by'] = $data['created_by'];
        }

        return MeetupEventObserver::batched(fn (): Collection => collect($dates)->map(
            fn (Carbon $occurrence): MeetupEvent => MeetupEvent::create([
                ...$shared,
                'start' => $occurrence,
                'end' => $duration === null ? null : $occurrence->copy()->addSeconds($duration),
            ]),
        ));
    }
}
