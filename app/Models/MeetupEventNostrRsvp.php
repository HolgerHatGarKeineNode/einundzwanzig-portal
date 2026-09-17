<?php

namespace App\Models;

use App\Console\Commands\Nostr\IngestNostrRsvps;
use App\Enums\NostrRsvpStatus;
use Database\Factories\MeetupEventNostrRsvpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The newest valid kind 31925 RSVP of one Nostr key for one meetup event (D12).
 *
 * Written only by {@see IngestNostrRsvps}; read by
 * {@see \App\Support\MeetupEventAttendance}. Never mirrored into the attendee JSON —
 * see the migration for why.
 *
 * @property int $id
 * @property int $meetup_event_id
 * @property string $pubkey
 * @property string $nostr_event_id
 * @property string $d_tag
 * @property NostrRsvpStatus $status
 * @property int $rsvp_created_at
 * @property int|null $user_id
 */
class MeetupEventNostrRsvp extends Model
{
    /** @use HasFactory<MeetupEventNostrRsvpFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'meetup_event_id',
        'pubkey',
        'nostr_event_id',
        'd_tag',
        'status',
        'rsvp_created_at',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NostrRsvpStatus::class,
            'rsvp_created_at' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MeetupEvent, $this>
     */
    public function meetupEvent(): BelongsTo
    {
        return $this->belongsTo(MeetupEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
