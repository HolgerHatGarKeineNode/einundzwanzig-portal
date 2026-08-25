<?php

namespace App\Console\Commands\Database;

use App\Models\City;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Prueft den Staedte-Bestand auf die sechs Fehlerformen, die im Betrieb tatsaechlich
 * vorkommen — und schreibt dabei nichts.
 *
 * ## Warum read-only, und warum das keine Bequemlichkeit ist
 *
 * Ein Pruefkommando, das repariert, landet irgendwann im Scheduler. Dann korrigiert es
 * unbeaufsichtigt Daten, deren Korrektur eine fachliche Entscheidung war. Zwei der acht
 * Befunde vom 2026-08-25 waren genau das: `Kopparberg` steht als deutsche Stadt, liegt
 * aber in Schweden — gehoert das Land korrigiert oder die Koordinate? Und
 * `to be announced` ist ueberhaupt keine Stadt. Beides kann nur ein Mensch entscheiden,
 * und ein Automat, der es trotzdem tut, tut es falsch und unbemerkt.
 *
 * ## Wofuer er wirklich da ist
 *
 * Nicht fuer diesen einen Lauf: bei 305 Staedten findet man acht Faelle auch von Hand.
 * Sondern dafuer, dass er **mitwaechst**. Steht der Import aus Issue #33 an und der
 * Bestand vervielfacht sich, ist das die einzige Pruefung, die dann noch skaliert.
 *
 * ## Die Reverse-Geocoding-Pruefung
 *
 * `--reverse` haelt jede Koordinate gegen Nominatim und vergleicht das Land dort mit
 * `country_id`. Das ist die einzige Pruefung, die einen Ort findet, der plausibel
 * aussieht und trotzdem im falschen Land steht. Sie kostet eine Anfrage pro Stadt und
 * laeuft deshalb mit der Bulk-Bremse (4/Minute, wie es die Nominatim-Policy fuer
 * Skripte verlangt) — bei 305 Staedten gut 75 Minuten. Ohne das Flag laeuft der Rest in
 * Sekunden.
 */
#[Signature('cities:audit {--reverse : Jede Koordinate gegen Nominatim halten (langsam: 4 Anfragen/Minute)} {--json : Befunde als JSON ausgeben}')]
#[Description('Prueft Staedte auf Namensdubletten, kaputte Koordinaten und Land-Widersprueche. Schreibt nichts.')]
class AuditCities extends Command
{
    /**
     * Ab welcher Naehe zwei gleichnamige Orte im selben Land verdaechtig sind.
     *
     * Kalibriert am kleinsten real gemessenen Abstand zwischen zwei gleichnamigen
     * Gemeinden (39,4 km, Georgetown/Indiana) — mit doppeltem Sicherheitsabstand.
     * Begruendung an `suspiciouslyCloseNamesakes()`.
     */
    private const NAMESAKE_THRESHOLD_KM = 20.0;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $findings = [];

    public function handle(): int
    {
        $this->duplicateNames();
        $this->suspiciouslyCloseNamesakes();
        $this->untrimmedNames();
        $this->coordinatesOutOfRange();
        $this->nullIsland();
        $this->sharedCoordinates();

        if ($this->option('reverse')) {
            $this->countryMismatch();
        }

        return $this->report();
    }

    /**
     * Derselbe Ort, zweimal eingetippt — und der Fall, in dem das nicht entscheidbar ist.
     *
     * Zeilenweise, nicht gruppenweise, aus demselben Grund wie in der Migration von
     * 2026-08-25: Eine Gruppierung nach `LOWER(TRIM(name))` wirft acht echte Gemeinden
     * namens `Neuenkirchen` und eine einzelne `'Neuenkirchen '` in denselben Topf. Der
     * `reviewer` hat reproduziert, dass die Migration daraufhin neun Zeilen zu einer
     * machte. Dieses Kommando haette denselben Fall als eine Dublette gemeldet und damit
     * zu einem Merge eingeladen, der sieben Orte kostet.
     *
     * Zwei Befunde statt einem:
     *
     * - **`namensdubletten`** — eine verschmutzte Zeile hat **genau einen** sauberen
     *   Zwilling. Das ist derselbe Ort, zweimal eingetippt; die Migration loest ihn auf.
     * - **`mehrdeutige_dublette`** — eine verschmutzte Zeile hat **mehrere** Zwillinge.
     *   Welcher gemeint war, weiss niemand. Die Migration laesst den Fall bewusst
     *   stehen, und dieser Befund ist die Stelle, an der ein Mensch davon erfaehrt.
     *
     * Gleichnamige Orte ohne Whitespace-Problem sind **kein** Befund. Sie sind der
     * Grund, warum es diesen Plan gibt — und ein Kommando, das im Normalbetrieb immer
     * anschlaegt, wird nach der dritten Woche ignoriert.
     */
    private function duplicateNames(): void
    {
        DB::table('cities')
            ->whereRaw('name <> TRIM(name)')
            ->orderBy('id')
            ->get(['id', 'name', 'country_id'])
            ->each(function (object $zeile): void {
                $zwillinge = DB::table('cities')
                    ->where('country_id', $zeile->country_id)
                    ->where('name', trim($zeile->name))
                    ->where('id', '<>', $zeile->id)
                    ->orderBy('id')
                    ->pluck('id');

                if ($zwillinge->isEmpty()) {
                    return;
                }

                $this->add($zwillinge->count() === 1 ? 'namensdubletten' : 'mehrdeutige_dublette', [
                    'id' => $zeile->id,
                    'name' => $zeile->name,
                    'zwillinge' => $zwillinge->all(),
                ]);
            });
    }

    /**
     * Gleichnamige Orte im selben Land, die zu nah beieinander liegen, um zwei zu sein.
     *
     * Diese Pruefung schliesst die Luecke, die das zweite Gate benannt hat: zwei Zeilen
     * `'Traunstein '` ohne sauberen Zwilling werden von der Migration beide getrimmt und
     * sind danach buchstabengleich — das Leerzeichen-Signal ist weg, und keine der
     * anderen Kategorien greift mehr. Ohne diese Pruefung stuenden sie fuer immer
     * unbemerkt da.
     *
     * ## Die Schwelle ist gemessen, nicht geraten
     *
     * Gleichnamige Orte existieren wirklich, und sie duerfen kein Befund sein — das ist
     * die Kernaussage dieses ganzen Plans. Die Frage ist also nicht „gleicher Name?",
     * sondern „zu nah, um zwei verschiedene Orte zu sein?". Gemessen an Nominatim am
     * 2026-08-25 ueber alle Paare gleichnamiger Orte:
     *
     *   Georgetown / Indiana ..... kleinster Abstand 39,4 km
     *   Salem / Indiana .......... kleinster Abstand 45,2 km
     *   Neuenkirchen / Niedersachsen  kleinster Abstand 48,5 km
     *
     * Der engste reale Fall liegt bei **39 km**. Eine Schwelle von 20 km trennt damit
     * mit doppeltem Sicherheitsabstand: naeher zusammen heisst unterschiedliche
     * Geocoding-Quelle, Rathaus gegen Ortsmittelpunkt, oder schlicht derselbe Ort
     * zweimal — nicht zwei Gemeinden.
     *
     * Sie **entscheidet nicht, sie meldet**. Eine ausgedehnte Stadt kann intern weiter
     * auseinanderliegen als 20 km; eine Automatik, die daraus einen Merge macht, waere
     * derselbe stille Fehler, den dieser Plan gerade beseitigt hat.
     */
    private function suspiciouslyCloseNamesakes(): void
    {
        $kandidaten = DB::table('cities')
            ->selectRaw('country_id, LOWER(TRIM(name)) as normalised, COUNT(*) as anzahl')
            ->groupByRaw('country_id, LOWER(TRIM(name))')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($kandidaten as $gruppe) {
            $zeilen = DB::table('cities')
                ->where('country_id', $gruppe->country_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$gruppe->normalised])
                ->orderBy('id')
                ->get(['id', 'name', 'latitude', 'longitude']);

            foreach ($zeilen as $i => $a) {
                foreach ($zeilen->slice($i + 1) as $b) {
                    $km = $this->distanceInKm(
                        (float) $a->latitude, (float) $a->longitude,
                        (float) $b->latitude, (float) $b->longitude,
                    );

                    if ($km <= self::NAMESAKE_THRESHOLD_KM) {
                        $this->add('gleichnamig_und_nah', [
                            'ids' => [$a->id, $b->id],
                            'name' => trim($a->name),
                            'entfernung_km' => round($km, 1),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Luftlinie in Kilometern (Haversine).
     */
    private function distanceInKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($h)));
    }

    /**
     * Namen mit aeusseren Leerzeichen.
     *
     * Fuer sich harmlos, als Muster nicht: ein unsichtbares Zeichen macht aus derselben
     * Stadt zwei Datensaetze, weil jede Namenssuche exakt vergleicht. Genau so entstand
     * `'Offenburg '` neben `'Offenburg'`.
     */
    private function untrimmedNames(): void
    {
        City::query()
            ->whereRaw('name <> TRIM(name)')
            ->get(['id', 'name', 'country_id'])
            ->each(fn (City $city) => $this->add('namen_mit_leerzeichen', [
                'id' => $city->getKey(),
                'name' => $city->name,
            ]));
    }

    /**
     * Koordinaten ausserhalb des gueltigen Wertebereichs.
     *
     * Der reale Fall dahinter: `Uznach SG` trug `717943 / 231527` — Schweizer
     * Landeskoordinaten (LV03), direkt in die WGS84-Spalten eingetragen.
     */
    private function coordinatesOutOfRange(): void
    {
        City::query()
            ->whereRaw('latitude < -90 OR latitude > 90 OR longitude < -180 OR longitude > 180')
            ->get(['id', 'name', 'latitude', 'longitude'])
            ->each(fn (City $city) => $this->add('koordinate_ausserhalb', [
                'id' => $city->getKey(),
                'name' => $city->name,
                'latitude' => (float) $city->latitude,
                'longitude' => (float) $city->longitude,
            ]));
    }

    /**
     * 0/0 — der Punkt im Atlantik, an dem alles landet, was nie eine Koordinate bekam.
     */
    private function nullIsland(): void
    {
        City::query()
            ->where('latitude', 0)
            ->where('longitude', 0)
            ->get(['id', 'name', 'country_id'])
            ->each(fn (City $city) => $this->add('nullinsel', [
                'id' => $city->getKey(),
                'name' => $city->name,
            ]));
    }

    /**
     * Verschiedene Staedte auf demselben Punkt.
     *
     * Meist ein Kopierfehler oder eine uebernommene Vorbelegung. Nicht immer falsch —
     * deshalb ein Befund und keine Korrektur.
     */
    private function sharedCoordinates(): void
    {
        DB::table('cities')
            ->selectRaw('latitude, longitude, COUNT(*) as anzahl')
            ->groupBy('latitude', 'longitude')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $gruppe): void {
                $ids = DB::table('cities')
                    ->where('latitude', $gruppe->latitude)
                    ->where('longitude', $gruppe->longitude)
                    ->orderBy('id')
                    ->pluck('id');

                $this->add('gleiche_koordinaten', [
                    'latitude' => (float) $gruppe->latitude,
                    'longitude' => (float) $gruppe->longitude,
                    'ids' => $ids->all(),
                ]);
            });
    }

    /**
     * Das Land unter den Koordinaten gegen `country_id`.
     *
     * Fail-soft: eine Stadt, deren Lookup scheitert, wird als `nicht_pruefbar` gemeldet
     * und nicht als Fehler. Ein Netzproblem darf keinen Datenbefund erfinden.
     */
    private function countryMismatch(): void
    {
        $staedte = City::query()->with('country')->orderBy('id')->get();
        $bar = $this->output->createProgressBar($staedte->count());

        foreach ($staedte as $city) {
            $bar->advance();

            $lat = (float) $city->latitude;
            $lon = (float) $city->longitude;

            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || ($lat === 0.0 && $lon === 0.0)) {
                continue; // schon als eigener Befund gemeldet
            }

            $land = $this->reverseCountryCode($lat, $lon);

            if ($land === null) {
                $this->add('nicht_pruefbar', ['id' => $city->getKey(), 'name' => $city->name]);

                continue;
            }

            if ($land !== mb_strtolower((string) $city->country?->code)) {
                $this->add('falsches_land', [
                    'id' => $city->getKey(),
                    'name' => $city->name,
                    'eingetragen' => mb_strtolower((string) $city->country?->code),
                    'laut_osm' => $land,
                ]);
            }
        }

        $bar->finish();
        $this->newLine(2);
    }

    /**
     * Der Laendercode unter einem Punkt, oder null wenn die Auskunft ausbleibt.
     *
     * Nominatims `reverse` hat im NominatimClient keine eigene Methode — die dortigen
     * `search()`/`lookup()` loesen die andere Richtung auf. Der Aufruf steht deshalb
     * hier, mit derselben Bremse von 15 Sekunden, die `NominatimClient::forBulk()` fuer
     * Skripte setzt. Waere er dort eingebaut, muesste der Client eine Richtung koennen,
     * die sonst niemand braucht.
     */
    private function reverseCountryCode(float $lat, float $lon): ?string
    {
        try {
            $antwort = Http::withHeaders(['User-Agent' => config('app.name').' city audit'])
                ->timeout(30)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'jsonv2',
                    'zoom' => 10,
                    'addressdetails' => 1,
                ]);

            usleep(15_000 * 1000); // dieselbe Bulk-Bremse wie NominatimClient::forBulk()

            if (! $antwort->successful()) {
                return null;
            }

            $code = $antwort->json('address.country_code');

            return is_string($code) && $code !== '' ? mb_strtolower($code) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $eintrag
     */
    private function add(string $kategorie, array $eintrag): void
    {
        $this->findings[$kategorie][] = $eintrag;
    }

    /**
     * Exitcode 1 bei Befunden, damit ein Aufruf in CI oder Cron etwas bedeutet.
     */
    private function report(): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($this->findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $this->findings === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($this->findings === []) {
            $this->info('Keine Befunde. '.City::count().' Staedte geprueft.');

            return self::SUCCESS;
        }

        foreach ($this->findings as $kategorie => $eintraege) {
            $this->newLine();
            $this->warn(str_replace('_', ' ', $kategorie).': '.count($eintraege));

            foreach ($eintraege as $eintrag) {
                $this->line('  '.json_encode($eintrag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        $this->newLine();
        $this->warn('Der Befehl schreibt nichts. Was zu tun ist, entscheidet ein Mensch.');

        return self::FAILURE;
    }
}
