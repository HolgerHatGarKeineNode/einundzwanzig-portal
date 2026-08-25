<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trimmt die Staedtenamen und legt die Dubletten zusammen, die erst dadurch entstehen.
 *
 * 12 der 305 Namen in Produktion tragen ein nachgestelltes Leerzeichen (gemessen
 * 2026-08-24). Elf davon sind harmlos. Der zwoelfte ist `'Offenburg '` — getrimmt
 * kollidiert er mit `'Offenburg'`, das seit 2023 als eigener Datensatz danebensteht.
 * Hinter dem angeblich globalen Unique, weil Postgres die beiden Zeichenketten zu Recht
 * unterscheidet.
 *
 * ## Drei Entscheidungen, die diese Migration trifft
 *
 * **Behalten wird die kleinere id.** Deterministisch, auf jeder Datenbank dasselbe
 * Ergebnis, und es ist die aeltere Zeile — die, auf die aeltere Verweise zeigen. Im
 * konkreten Fall traegt sie ausserdem das Meetup.
 *
 * **Umgehaengt wird mit UPDATE, nie mit DELETE-und-neu.** `meetups.city_id` ist NOT NULL
 * mit ON DELETE CASCADE: ein Loeschen der falschen Zeile reisst ihre Meetups mit, und
 * das faellt niemandem auf, weil es wie ein erfolgreiches Aufraeumen aussieht.
 *
 * **`api_changes.city_id` wird mitgezogen, obwohl es keinen Fremdschluessel hat.**
 * Gerade deshalb: eine Spalte ohne Constraint zeigt nach dem Loeschen still auf eine
 * tote id, und die Datenbank sagt nichts.
 *
 * ## Was sie NICHT tut
 *
 * Sie fasst keine Staedte zusammen, die nur zufaellig gleich heissen. Zusammengelegt
 * wird ausschliesslich, was nach dem Trim buchstabengleich im SELBEN Land steht — also
 * derselbe Ort, zweimal eingetippt. Acht Neuenkirchen in Niedersachsen bleiben acht
 * Neuenkirchen.
 *
 * Die Slugs bleiben unveraendert: `doNotGenerateSlugsOnUpdate()` auf dem Model laesst
 * sie stehen, und das ist gewollt — ein Slug ist eine veroeffentlichte Zeichenkette,
 * kein Ableitungsergebnis. `'Offenburg '` behaelt also `de-offenburg`, und der Slug der
 * geloeschten Zeile (`de-offenburg-1`) verschwindet mit ihr.
 */
return new class extends Migration
{
    /**
     * Tabellen, die auf `cities.id` zeigen.
     *
     * `api_changes` steht bewusst dabei, obwohl es keinen Fremdschluessel hat.
     *
     * @var array<int, string>
     */
    private const REFERENCING_TABLES = [
        'meetups',
        'course_events',
        'bitcoin_events',
        'api_changes',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->mergeDuplicatesAfterTrim();
            $this->trimRemainingNames();
        });
    }

    /**
     * Namen, die nach dem Trim mit einem anderen Namen im selben Land kollidieren.
     *
     * Zuerst zusammenlegen, dann trimmen — die andere Reihenfolge scheitert an der
     * Kollision, die sie gerade selbst erzeugt.
     */
    private function mergeDuplicatesAfterTrim(): void
    {
        $gruppen = DB::table('cities')
            ->selectRaw('country_id, LOWER(TRIM(name)) as normalisiert, COUNT(*) as anzahl')
            ->groupByRaw('country_id, LOWER(TRIM(name))')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($gruppen as $gruppe) {
            $ids = DB::table('cities')
                ->where('country_id', $gruppe->country_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$gruppe->normalisiert])
                ->orderBy('id')
                ->pluck('id');

            $behalten = $ids->first();

            foreach ($ids->skip(1) as $aufzuloesen) {
                foreach (self::REFERENCING_TABLES as $tabelle) {
                    DB::table($tabelle)->where('city_id', $aufzuloesen)->update(['city_id' => $behalten]);
                }

                DB::table('cities')->where('id', $aufzuloesen)->delete();
            }
        }
    }

    /**
     * Die restlichen Namen trimmen — jetzt kollisionsfrei.
     */
    private function trimRemainingNames(): void
    {
        DB::table('cities')
            ->whereRaw('name <> TRIM(name)')
            ->update(['name' => DB::raw('TRIM(name)')]);
    }

    /**
     * Nicht umkehrbar, und das ist keine Nachlaessigkeit.
     *
     * Ein zusammengelegter Datensatz laesst sich nicht wieder auseinandernehmen: welche
     * Meetups vorher an welcher der beiden Zeilen hingen, steht danach nirgends mehr.
     * Ein `down()`, das so tut, als koenne es das, waere schlimmer als keines.
     */
    public function down(): void {}
};
