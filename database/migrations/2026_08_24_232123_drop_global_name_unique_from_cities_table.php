<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der globale Unique auf `cities.name` faellt — ersatzlos, ohne Nachfolger auf
 * Namensbasis.
 *
 * Ein Ortsname ist auf KEINER Verwaltungsebene eindeutig. Gemessen an Nominatim am
 * 2026-08-25: acht Gemeinden namens "Neuenkirchen" allein in Niedersachsen, zwei davon
 * im selben Landkreis Osnabrueck; sechs "Georgetown" in Indiana, vier "Salem". Ein
 * zusammengesetzter Unique ueber (country_id, region_id, name) — der naheliegende
 * Nachfolger, vom Melder in Issue #33 selbst vorgeschlagen — haette diese Daten
 * abgewiesen. Ein Constraint, der korrekte Zeilen verbietet, ist kein Schutz.
 *
 * Der Index war ohnehin nie so dicht, wie er aussah: 12 der 305 Namen in Produktion
 * tragen ein nachgestelltes Leerzeichen, und `'Offenburg'` (id 233) existiert seit 2023
 * neben `'Offenburg '` (id 184) — hinter dem angeblich globalen Unique, weil Postgres
 * die beiden Zeichenketten zu Recht unterscheidet.
 *
 * An seine Stelle tritt kein Index, sondern ein Verfahren: City::resolveOrCreate()
 * loest eindeutig auf oder scheitert sichtbar, und eine Neuanlage neben einem
 * gleichnamigen Ort verlangt einen Identifier — die OSM-Referenz oder eine
 * ausdrueckliche Bestaetigung.
 *
 * Der OSM-Index wird im selben Zug unique. In Postgres sind NULLs in einem
 * Unique-Index per Default verschieden, deshalb erlaubt das weiterhin beliebig viele
 * Staedte OHNE Referenz (heute 299 von 305) und verhindert nur doppelte MIT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropUnique('cities_name_unique');

            $table->dropIndex('cities_osm_type_osm_id_index');
            $table->unique(['osm_type', 'osm_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropUnique(['osm_type', 'osm_id']);
            $table->index(['osm_type', 'osm_id']);
        });

        /*
         * `name` bekommt seinen Unique NICHT zurueck. Sobald zwei gleichnamige Staedte
         * angelegt wurden — und genau dafuer ist diese Migration da — scheitert er, und
         * ein down(), das im Normalfall fehlschlaegt, ist schlimmer als eines, das die
         * Einbahnstrasse benennt. Wer wirklich zurueck will, legt die Dubletten vorher
         * von Hand zusammen und setzt den Index selbst.
         */
    }
};
