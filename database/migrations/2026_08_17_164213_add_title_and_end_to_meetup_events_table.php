<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #4, points 1 and 2: meetup events get their own title and an end time.
 *
 * Both nullable, deliberately. Existing events have neither, and a meetup event
 * without a title is a normal case — it simply carries the meetup's name, which is
 * how every event in the database works today.
 *
 * `end` is the end of THIS event. The existing `recurrence_end_date` is something
 * else entirely: the date a recurring series stops producing occurrences. Naming
 * them apart matters — conflating them is how a two-hour meetup ends up looking
 * like it runs for six months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->string('title')
                ->nullable()
                ->after('meetup_id');

            $table->dateTime('end')
                ->nullable()
                ->after('start');
        });
    }

    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->dropColumn(['title', 'end']);
        });
    }
};
