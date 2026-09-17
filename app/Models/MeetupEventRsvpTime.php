<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When a signed-in user last answered an RSVP through the portal (D12a).
 *
 * Exists only so that "the newer answer wins" can be decided between a portal answer
 * and a Nostr RSVP of the same linked user. The table migration explains why this is a
 * row per (event, user) instead of a change to the attendee JSON, and the backfill
 * migration `2026_09_18_090000_backfill_meetup_event_rsvp_times` explains what a MISSING
 * row means (the account never answered in the portal).
 *
 * @property int $id
 * @property int $meetup_event_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $answered_at
 */
class MeetupEventRsvpTime extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'meetup_event_id',
        'user_id',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /**
     * Stamp "this user answered this event through the portal, now".
     *
     * An upsert on the unique (event, user) pair, so two concurrent answers cannot
     * produce two rows or lose the later time to a read-modify-write. Called next to —
     * never instead of — the existing attendee list write, whatever the answer was:
     * a withdrawal is an answer too.
     */
    public static function record(MeetupEvent $meetupEvent, int $userId): void
    {
        static::query()->upsert(
            [[
                'meetup_event_id' => $meetupEvent->id,
                'user_id' => $userId,
                'answered_at' => now(),
            ]],
            ['meetup_event_id', 'user_id'],
            ['answered_at'],
        );

        $meetupEvent->unsetRelation('rsvpTimes');
        $meetupEvent->forgetAttendance();
    }

    /**
     * @return BelongsTo<MeetupEvent, $this>
     */
    public function meetupEvent(): BelongsTo
    {
        return $this->belongsTo(MeetupEvent::class);
    }
}
