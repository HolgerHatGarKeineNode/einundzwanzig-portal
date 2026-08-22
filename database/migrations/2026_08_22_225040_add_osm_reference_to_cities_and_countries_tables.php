<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issues #11 und #12: Staedte und Laender bekommen eine OpenStreetMap-Referenz.
 *
 * Bewusst dieselben sechs Spalten wie die Event-Tabellen seit
 * 2026_08_17_171226_add_osm_location_to_event_tables — ein zweites, abweichendes Muster
 * fuer dieselbe Sache waere die teuerste Art, sich das Leben schwer zu machen.
 * `osm_type` + `osm_id` identifizieren gemeinsam ein Objekt; keins von beiden ist allein
 * eindeutig. Name, Adresse und Koordinaten sind eine Kopie, damit eine Liste mit fuenfzig
 * Eintraegen nicht fuenfzig Nominatim-Anfragen ausloest — was deren Policy ohnehin
 * verboete.
 *
 * Neu gegenueber den Events: `wikidata` und `wikipedia`. Nominatim liefert beide mit
 * `extratags=1` direkt mit (gemessen an R62422: "Q64" und "de:Berlin"), ein eigener
 * Wikidata-Client ist dafuer nicht noetig. `wikipedia` ist der OSM-Slug, kein Link —
 * die URL entsteht erst im Accessor.
 *
 * Alles nullable. Jede bestehende Stadt und jedes bestehende Land bleibt gueltig.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = ['cities', 'countries'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('osm_type', 16)->nullable();
                $blueprint->unsignedBigInteger('osm_id')->nullable();
                $blueprint->string('osm_name')->nullable();
                $blueprint->string('osm_address')->nullable();
                // Decimal statt float: Koordinaten werden verglichen und dedupliziert,
                // und Float-Gleichheit ist eine Falle. 7 Nachkommastellen sind rund 1 cm.
                $blueprint->decimal('osm_lat', 10, 7)->nullable();
                $blueprint->decimal('osm_lon', 10, 7)->nullable();
                // Wikidata-Q-ID, z. B. "Q64". 32 Zeichen sind reichlich.
                $blueprint->string('wikidata', 32)->nullable();
                // OSM-Slug der Form "de:Berlin" — Sprachpraefix plus Artikeltitel.
                $blueprint->string('wikipedia')->nullable();

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
                    'wikidata', 'wikipedia',
                ]);
            });
        }
    }
};
