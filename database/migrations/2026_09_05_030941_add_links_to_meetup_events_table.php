<?php

use App\Models\MeetupEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #70: an event carries several links, not one.
 *
 * ## Why a JSON column and not a `meetup_event_links` table
 *
 * The list is short (at most {@see MeetupEvent::MAX_LINKS}), it is
 * always read whole, it is always written whole with the event that owns it, and
 * nothing joins on it or points at a single entry. A child table would buy
 * per-row integrity and indexable URLs that no query in this application asks
 * for, and would cost a second write path, a relation to eager-load in every
 * place an event is serialised (the API list endpoint alone maps every event of a
 * month), and an ordering column to keep the organiser's sequence. `attendees`
 * and `might_attendees` on this same table are already JSON lists for the same
 * reasons, so this is the shape the row is written in, not a new one.
 *
 * What it costs, stated plainly: `WHERE` on a single URL needs a JSON path
 * expression and cannot use an index, and the database will not stop a bad writer
 * from storing a malformed entry — validation and
 * {@see MeetupEvent::normaliseLinks()} are the only guards. Should
 * anyone ever need to ask "which events link to meetup.com", that is the moment
 * to reconsider, and moving to a child table then is a migration over at most
 * five rows per event.
 *
 * ## The fate of the old `link` column: KEPT and DEPRECATED
 *
 * It is not dropped here, because three consumers still read it and none of them
 * is part of this issue: `DownloadMeetupCalendar` (the ICS `URL:` property),
 * `Meetup::nextEvent()`'s payload, and the two MCP tools, which also still WRITE
 * it. It stays the mirror of the first entry, maintained by the model on every
 * save (see {@see MeetupEvent::booted()}), so those three keep working
 * unchanged and no consumer has to move on this issue's schedule.
 *
 * Dropping it is a later migration, once those consumers read `links` instead. A
 * new writer should not use it.
 *
 * ## The backfill
 *
 * Every event that has a link today must have it as its first entry afterwards —
 * that is the point of the whole migration, so it runs here rather than lazily on
 * read. Rows with a null or empty `link` get nothing: `links` stays NULL, which
 * the model reads as "never written in the new shape" and is distinct from an
 * explicitly emptied `[]`.
 *
 * `up()` is written to be re-runnable (the column guard, and a backfill that only
 * touches rows whose `links` is still NULL) so a test can seed old-shape rows and
 * call it against them — an empty table proves nothing about a data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('meetup_events', 'links')) {
            Schema::table('meetup_events', function (Blueprint $table) {
                // Appended at the end of the row rather than ->after('link') — same
                // reasoning as the cancelled_at migration next door: on MySQL the last
                // position is the case that has been ALGORITHM=INSTANT the longest.
                $table->json('links')->nullable();
            });
        }

        DB::table('meetup_events')
            ->whereNull('links')
            ->whereNotNull('link')
            ->where('link', '!=', '')
            ->select('id', 'link')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('meetup_events')
                        ->where('id', $row->id)
                        // json_encode with default flags, i.e. the same encoding
                        // Eloquent's `array` cast produces — so a backfilled row and a
                        // row written through the model are byte-identical, not merely
                        // equivalent after decoding.
                        ->update(['links' => json_encode([['url' => $row->link]])]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->dropColumn('links');
        });
    }
};
