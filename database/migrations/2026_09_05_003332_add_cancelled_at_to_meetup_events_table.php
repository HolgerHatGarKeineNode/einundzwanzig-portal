<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #56: a cancelled event has to keep existing, or the calendar feed can
 * never say it was cancelled.
 *
 * Until now the organiser's only tool was deletion, and a deleted row cannot be
 * reported to anybody — the entry just stopped appearing in the next fetch, and
 * whether a subscriber's client removed it was up to that client. Cancellation
 * becomes a state the feed can carry; deletion stays deletion.
 *
 * A nullable timestamp, not a boolean and not a status enum. Null is "not
 * cancelled", and a non-null value answers "when", which is the question that
 * always follows a cancellation. Same shape and same reasoning as `rejected_at`
 * on webhook_subscriptions.
 *
 * Safe on a populated table: a NULLable column with no default writes nothing to
 * any existing row, so there is no backfill and no lock held while one runs. It
 * is deliberately appended at the END of the row instead of using ->after() like
 * the neighbouring meetup_events migrations — on MySQL, adding a column in the
 * last position is the case that has been ALGORITHM=INSTANT (metadata only, no
 * table rebuild) the longest, while adding one in the middle only became instant
 * in a later 8.0 release. Column order is cosmetic; rebuilding every row of a
 * live table is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
