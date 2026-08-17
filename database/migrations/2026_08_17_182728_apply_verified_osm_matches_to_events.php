<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the OpenStreetMap places that were verified against production on 2026-08-17.
 *
 * Why a migration with fixed values rather than a live geocoding run: the matching itself
 * is not reproducible on demand. Nominatim's answers change, its rate limits make a
 * deploy-time run impractical, and every one of these rows was reviewed by hand. Freezing
 * the verified result is the honest way to ship it.
 *
 * Only 16 of 96 venues are in here. The full run scored 23 %, far below the threshold —
 * not because the scoring was too strict but because most venue rows are not places at
 * all: event names ("Süd-Niedersachsen Meetup"), regions ("Zwischen Templin - Lychen"),
 * private rooms. Those keep their free-text location, which for them is the correct
 * answer rather than a stopgap.
 *
 * The six that were dropped between the first and second export are the reason this file
 * is short: a lone mediocre hit used to count as confident, which mapped "Volkshochschule
 * Kassel (Landkreis)" onto "Volkshochschule Hofgeismar" — a different town. Being the only
 * answer is not evidence of being the right one.
 *
 * Safe on any database: a venue id that does not exist, or whose name has since changed,
 * is skipped in silence. Locally nothing matches and nothing happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('data/venue-osm-matches.json');

        // The venues table is dropped by a later migration. On a fresh database this one
        // still runs first and finds it; anywhere else there is nothing left to read.
        if (! is_file($path) || ! Schema::hasTable('venues')) {
            return;
        }

        $matches = json_decode((string) file_get_contents($path), true);

        if (! is_array($matches)) {
            return;
        }

        foreach ($matches as $match) {
            $venue = DB::table('venues')->where('id', $match['venue_id'])->first(['id', 'name']);

            /*
             * The name is checked, not just the id. Ids are stable in production, but this
             * file will also run on databases where the same id means something else
             * entirely — and writing a Dresden museum onto a Viennese bar would be worse
             * than doing nothing.
             */
            if ($venue === null || $venue->name !== $match['venue_name']) {
                continue;
            }

            $fields = [
                'osm_type' => $match['osm_type'],
                'osm_id' => $match['osm_id'],
                'osm_name' => $match['osm_name'],
                'osm_address' => $match['osm_address'],
                'osm_lat' => $match['osm_lat'],
                'osm_lon' => $match['osm_lon'],
            ];

            foreach (['course_events', 'bitcoin_events'] as $table) {
                DB::table($table)
                    ->where('venue_id', $venue->id)
                    // Never overwrite a place someone entered by hand.
                    ->whereNull('osm_id')
                    ->update($fields);
            }
        }
    }

    public function down(): void
    {
        $path = database_path('data/venue-osm-matches.json');

        if (! is_file($path)) {
            return;
        }

        $matches = json_decode((string) file_get_contents($path), true);

        if (! is_array($matches)) {
            return;
        }

        // Reversible, unlike the tag merge: clearing these columns loses nothing that was
        // not derived automatically in the first place.
        $blank = array_fill_keys(
            ['osm_type', 'osm_id', 'osm_name', 'osm_address', 'osm_lat', 'osm_lon'],
            null
        );

        foreach ($matches as $match) {
            foreach (['course_events', 'bitcoin_events'] as $table) {
                DB::table($table)
                    ->where('venue_id', $match['venue_id'])
                    ->where('osm_id', $match['osm_id'])
                    ->update($blank);
            }
        }
    }
};
