<?php

use App\Models\MeetupEventRsvpTime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid RSVP (D12a): WHEN a signed-in user last answered through the portal.
 *
 * A linked user can answer on two channels — the portal (REST API, landing page) and a
 * Nostr client (kind 31925) — and the newer answer wins. The Nostr side carries its own
 * `created_at`; the portal side never recorded a time, because the attendee lists are
 * plain `id_<userId>|<name>` strings in a JSON column.
 *
 * ## Why a table and not a column or a new JSON shape
 *
 * - A timestamp INSIDE the attendee entries would change a format that the REST API,
 *   the landing page, MergeUserAccounts and every stored row depend on.
 * - A JSON map column on `meetup_events` would be a second unlocked read-modify-write
 *   on the same row, with the same lost-update race as the lists themselves.
 * - One row per (event, user), written by an upsert, cannot lose an answer to a
 *   concurrent one, and it is written next to the existing list write without changing
 *   the list write itself ({@see MeetupEventRsvpTime::record()}).
 *
 * The "nullable timestamp" of the design is the ABSENCE of a row. What that absence MEANS
 * is decided one migration later: `2026_09_18_090000_backfill_meetup_event_rsvp_times`
 * gives every answer that already sits on a list the time of its own deployment, so from
 * then on a missing row means "this account never answered in the portal" rather than
 * "answered at an unknown time". Without that backfill an arbitrarily old Nostr answer
 * could overrule a portal answer given years earlier, which is not what "the newer answer
 * wins" says.
 *
 * `answered_at` moves on every portal answer, including "none": withdrawing is an answer
 * too, and it has to be able to beat an older Nostr "accepted".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetup_event_rsvp_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meetup_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('answered_at');

            $table->unique(['meetup_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetup_event_rsvp_times');
    }
};
