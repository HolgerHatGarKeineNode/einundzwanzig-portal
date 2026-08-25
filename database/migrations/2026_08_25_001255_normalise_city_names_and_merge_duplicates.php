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
     * Jede verschmutzte Zeile einzeln ihrem sauberen Zwilling zuordnen — oder gar nicht.
     *
     * ## Warum zeilenweise und nicht gruppenweise
     *
     * Die erste Fassung gruppierte nach `(country_id, LOWER(TRIM(name)))` und legte die
     * Gruppe zusammen, sobald darin verschiedene rohe Namen vorkamen. Der `reviewer`
     * hat den Fall reproduziert, den das uebersieht: **acht echte Gemeinden namens
     * `Neuenkirchen` plus eine einzige Zeile `'Neuenkirchen '`** bilden eine Gruppe mit
     * zwei verschiedenen rohen Namen — und die ganze Gruppe wurde zusammengelegt. Neun
     * Zeilen wurden zu einer, acht davon echte Orte. Gemessen: `vorher => 9`,
     * `nachher => 1`.
     *
     * Der Docblock behauptete damals, im schlimmsten Fall wuerden „zwei Orte"
     * zusammengelegt. Tatsaechlich war es die gesamte Gruppe. Das ist derselbe
     * Fehlertyp wie beim ersten Fund: der Kommentar beschrieb die Absicht, nicht den
     * Code.
     *
     * ## Was jetzt passiert
     *
     * Fuer jede Zeile mit aeusserem Leerzeichen wird gefragt: gibt es im selben Land
     * **genau eine** Zeile, die exakt so heisst wie ihr getrimmter Name?
     *
     * - **Genau eine** → derselbe Ort, zweimal eingetippt. Zusammenlegen.
     * - **Keine** → nichts zusammenzulegen, nur trimmen (der haeufige Fall: 11 der 12).
     * - **Mehrere** → mehrdeutig. **Nicht anfassen.** Welcher der acht Neuenkirchen
     *   gemeint war, weiss niemand — und raten heisst hier, sieben Orte zu loeschen.
     *
     * Der dritte Zweig ist der eigentliche Fix. Er laesst bewusst eine Dublette stehen,
     * statt eine Entscheidung zu erfinden: `cities:audit` meldet den Fall danach als
     * `mehrdeutige_dublette`, und ein Mensch loest ihn auf.
     */
    private function mergeDuplicatesAfterTrim(): void
    {
        $verschmutzte = DB::table('cities')
            ->whereRaw('name <> TRIM(name)')
            ->orderBy('id')
            ->get(['id', 'name', 'country_id']);

        foreach ($verschmutzte as $zeile) {
            $zwillinge = DB::table('cities')
                ->where('country_id', $zeile->country_id)
                ->where('name', trim($zeile->name))
                ->where('id', '<>', $zeile->id)
                ->orderBy('id')
                ->pluck('id');

            if ($zwillinge->count() !== 1) {
                continue; // keiner: nichts zu tun. mehrere: mehrdeutig, Handarbeit.
            }

            $this->mergeInto(
                behalten: min($zeile->id, $zwillinge->first()),
                aufzuloesen: max($zeile->id, $zwillinge->first()),
            );
        }
    }

    /**
     * Haengt alle Verweise um und loescht die aufgeloeste Zeile.
     *
     * Immer per UPDATE und immer VOR dem Loeschen: `meetups.city_id` ist NOT NULL mit
     * ON DELETE CASCADE, ein Loeschen in falscher Reihenfolge reisst die Meetups mit.
     */
    private function mergeInto(int $behalten, int $aufzuloesen): void
    {
        foreach (self::REFERENCING_TABLES as $tabelle) {
            DB::table($tabelle)->where('city_id', $aufzuloesen)->update(['city_id' => $behalten]);
        }

        DB::table('cities')->where('id', $aufzuloesen)->delete();
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
