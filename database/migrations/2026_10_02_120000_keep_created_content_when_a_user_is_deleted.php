<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting an account keeps the content it created (issue #150).
 *
 * Until now every `created_by` below cascaded: deleting a user took the cities,
 * meetups, events, lecturers and courses they had created with them — reference
 * data other people's meetups and events point at. That never happened only
 * because the delete form asked for a password no Nostr or LNURL account has.
 * Making deletion work makes the cascade reachable, so it goes first.
 *
 * `nullOnDelete` instead: the record stays and loses its creator. Every policy
 * compares `created_by` with the acting user, so an orphaned record is editable
 * by super-admins only, and the views already guard a missing `createdBy`.
 *
 * `down()` restores the cascade but leaves the columns nullable — rows orphaned
 * in the meantime would make a NOT NULL column fail to migrate back.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'cities',
        'lecturers',
        'meetups',
        'meetup_events',
        'courses',
        'course_events',
        'libraries',
        'library_items',
        'podcasts',
        'episodes',
        'self_hosted_services',
    ];

    /**
     * The expression index of 2026_08_26_134145 on `lower(name)`. See {@see withoutLowerNameIndex()}.
     */
    private const LOWER_NAME_INDEX = 'meetups_lower_name_unique';

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            $this->withoutLowerNameIndex($tableName, function () use ($tableName): void {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['created_by']);
                });

                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('created_by')->nullable()->change();
                    $table->foreign('created_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete()
                        ->cascadeOnUpdate();
                });
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            $this->withoutLowerNameIndex($tableName, function () use ($tableName): void {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['created_by']);
                });

                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('created_by')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete()
                        ->cascadeOnUpdate();
                });
            });
        }
    }

    /**
     * On SQLite both `dropForeign()` and `->change()` rebuild the table, and the
     * rebuild cannot re-create the expression index on `lower(name)` — the same
     * trap 2026_09_17_200000 documents. The index is dropped before and created
     * again afterwards. PostgreSQL alters the constraint in place and never
     * touches the index.
     */
    private function withoutLowerNameIndex(string $tableName, Closure $alter): void
    {
        $rebuildsTable = $tableName === 'meetups'
            && DB::getDriverName() === 'sqlite'
            && Schema::hasIndex('meetups', self::LOWER_NAME_INDEX);

        if ($rebuildsTable) {
            DB::statement(sprintf('DROP INDEX %s', self::LOWER_NAME_INDEX));
        }

        $alter();

        if ($rebuildsTable) {
            DB::statement(sprintf('CREATE UNIQUE INDEX %s ON meetups (LOWER(name))', self::LOWER_NAME_INDEX));
        }
    }
};
