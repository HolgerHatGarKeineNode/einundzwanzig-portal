<?php

use App\Console\Commands\Nostr\IngestNostrRsvps;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid RSVP (D12): the kind 31925 answers the portal reads back from Nostr.
 *
 * One row per (event, pubkey) — the newest valid RSVP of that key for that event, as
 * folded by {@see IngestNostrRsvps}. A key may publish several RSVPs under different
 * `d` tags (two clients, two devices); the fold keeps one, so the unique index is the
 * invariant and not an optimisation.
 *
 * ## Why this is a table and not a write into `meetup_events.attendees`
 *
 * The JSON lists are maintained by an unlocked read-modify-write
 * (MeetupEvent::setRsvpFor, the landing page component). A second writer running every
 * five minutes would race every portal answer and lose them silently. Nostr answers
 * therefore live here and are merged at READ time (App\Support\MeetupEventAttendance).
 *
 * ## Columns beyond the obvious
 *
 * - `d_tag`: needed to honour a NIP-09 deletion that addresses the RSVP by
 *   `a = 31925:<pubkey>:<d>` rather than by event id.
 * - `rsvp_created_at`: the event's own `created_at` as a unix integer — the value the
 *   "newest wins" fold compares, kept exact rather than pushed through a timezone.
 * - `user_id`: the portal account whose `users.nostr` is this pubkey's npub, or NULL.
 *   Re-resolved on every ingest run, so a link made (or moved by an account merge)
 *   after the RSVP arrived is picked up within one run. `nullOnDelete`, never cascade:
 *   deleting an account must not delete a public Nostr answer, it only turns it back
 *   into an unlinked one.
 * - `updated_at` doubles as "when the portal first saw this RSVP": the ingest writes a
 *   row only when the winning RSVP changes, and the attendance merge uses it to clamp a
 *   future-dated `created_at` (see MeetupEventAttendance::nostrAnsweredAt()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetup_event_nostr_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meetup_event_id')->constrained()->cascadeOnDelete();
            $table->char('pubkey', 64);
            $table->char('nostr_event_id', 64);
            $table->string('d_tag');
            $table->enum('status', ['accepted', 'tentative', 'declined']);
            $table->unsignedBigInteger('rsvp_created_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['meetup_event_id', 'pubkey']);
            $table->index('pubkey');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetup_event_nostr_rsvps');
    }
};
