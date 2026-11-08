<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NIP-52 publishing becomes opt-out: default on, and on for every existing meetup.
 *
 * This reverses the opt-in of 2026_08_29_170904. Nostr RSVPs (kind 31925) can only
 * address an event that exists on the relays as a kind 31923, and with opt-in 17 of
 * ~669 upcoming events had one (measured 2026-09-17). Leaders keep the switch on the
 * meetup edit form, and the meetup page tells them publishing is on.
 *
 * `up()` flips the column default AND sets every existing row to true — a leader who
 * had switched publishing off before is switched on again. That is the decision taken,
 * not a side effect; the page notice is how such a leader learns about it.
 *
 * `down()` restores the default `false` and deliberately does NOT touch row values.
 * Which meetups had opted in before `up()` ran is not recorded anywhere, so any
 * row-level rollback would be a guess — and resetting every row to false would switch
 * off the leaders who had opted in on their own. Rolling back leaves every meetup
 * opted in; switching individual meetups off is a data decision, not a schema one.
 */
return new class extends Migration
{
    /**
     * The expression index of 2026_08_26_134145 on `lower(name)`. See {@see changeDefault()}.
     */
    private const LOWER_NAME_INDEX = 'meetups_lower_name_unique';

    public function up(): void
    {
        $this->changeDefault(true);

        DB::table('meetups')->update(['nostr_publishing_enabled' => true]);
    }

    public function down(): void
    {
        $this->changeDefault(false);
    }

    /**
     * SQLite cannot alter a column default in place, so `->change()` rebuilds the table
     * and re-creates its indexes from what the schema reports. For the expression index
     * on `lower(name)` it reports no columns and the rebuild dies on
     * `create unique index "meetups_lower_name_unique" on "meetups" ()` — measured on
     * the test database. The index is therefore dropped before the rebuild and created
     * again, with its original statement, afterwards. PostgreSQL and MySQL alter the
     * default in place and never touch the index.
     */
    private function changeDefault(bool $default): void
    {
        $rebuildsTable = DB::getDriverName() === 'sqlite'
            && Schema::hasIndex('meetups', self::LOWER_NAME_INDEX);

        if ($rebuildsTable) {
            DB::statement(sprintf('DROP INDEX %s', self::LOWER_NAME_INDEX));
        }

        Schema::table('meetups', function (Blueprint $blueprint) use ($default) {
            $blueprint->boolean('nostr_publishing_enabled')->default($default)->change();
        });

        if ($rebuildsTable) {
            DB::statement(sprintf('CREATE UNIQUE INDEX %s ON meetups (LOWER(name))', self::LOWER_NAME_INDEX));
        }
    }
};
