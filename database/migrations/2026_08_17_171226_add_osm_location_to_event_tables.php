<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #4, point 6: events reference an OpenStreetMap node instead of a Venue row.
 *
 * The reporter's own wording is the design: keep a cached name and address alongside
 * the reference "so a simple view will not require to fetch it". A list of fifty events
 * must not turn into fifty Nominatim requests — and Nominatim's usage policy would not
 * permit that anyway.
 *
 * Everything nullable. The existing free-text location field stays and remains the right
 * answer for the cases the issue names itself: "TBA", "follow the Signal group".
 *
 * osm_type + osm_id together identify a node/way/relation; neither is unique alone.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = ['meetup_events', 'course_events', 'bitcoin_events'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('osm_type', 16)->nullable();
                $blueprint->unsignedBigInteger('osm_id')->nullable();
                $blueprint->string('osm_name')->nullable();
                $blueprint->string('osm_address')->nullable();
                // Decimal rather than float: coordinates are compared and deduplicated,
                // and float equality is a trap. 7 decimals is roughly 1 cm.
                $blueprint->decimal('osm_lat', 10, 7)->nullable();
                $blueprint->decimal('osm_lon', 10, 7)->nullable();

                $blueprint->index(['osm_type', 'osm_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['osm_type', 'osm_id']);
                $blueprint->dropColumn([
                    'osm_type', 'osm_id', 'osm_name', 'osm_address', 'osm_lat', 'osm_lon',
                ]);
            });
        }
    }
};
