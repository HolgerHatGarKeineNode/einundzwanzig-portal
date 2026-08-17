<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the venue: its city, address and map position now live on the events themselves.
 *
 * Runs last on purpose. Two earlier migrations still need the table — the verified OSM
 * matches read it to find their events, and the city/location copy reads name and street
 * out of it. Dropping it any sooner would silently produce events with no address at all.
 *
 * What is NOT touched here: the 41 media rows attached to `App\Models\Venue`. Deleting them
 * would destroy roughly 13 MB of photographs to save a foreign key, and there is no obvious
 * new owner — a venue hosted many events, so no single event inherits its picture. They are
 * counted and reported instead, and stay on disk until somebody decides where they belong.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['course_events', 'bitcoin_events'] as $table) {
            if (! Schema::hasColumn($table, 'venue_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('venue_id');
            });
        }

        if (Schema::hasTable('venues')) {
            Schema::drop('venues');
        }

        $this->reportOrphanedMedia();
    }

    /**
     * Say out loud what was left behind, so it does not become invisible debt.
     */
    private function reportOrphanedMedia(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $orphans = DB::table('media')->where('model_type', 'App\Models\Venue')->count();

        if ($orphans > 0) {
            echo "  Note: {$orphans} media rows still reference the removed Venue model.\n";
        }
    }

    /**
     * Brings the structure back so a rollback lands in a working database — but the rows do
     * not return. Their content was copied onto the events before the drop and is still
     * there; what is gone is the separate venue record, and that is the point of the change.
     */
    public function down(): void
    {
        if (! Schema::hasTable('venues')) {
            Schema::create('venues', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('city_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('street');
                $table->timestamps();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        foreach (['course_events', 'bitcoin_events'] as $table) {
            if (Schema::hasColumn($table, 'venue_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }
};
