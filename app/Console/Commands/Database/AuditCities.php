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
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $findings = [];

    public function handle(): int
    {
        $this->duplicateNames();
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
     * Gleicher Name (normalisiert) im selben Land — also derselbe Ort, zweimal eingetippt.
     *
     * Gleichnamige Orte in verschiedenen Laendern sind KEIN Befund, und gleichnamige
     * Orte im selben Land sind es nur dann, wenn sie buchstabengleich sind: acht
     * Gemeinden namens Neuenkirchen in Niedersachsen sind acht Gemeinden, kein Fehler.
     * Diese Pruefung meldet deshalb bewusst nur, was die Migration von 2026-08-25 auch
     * zusammenlegen wuerde — sonst waere sie nach dem naechsten Import dauerhaft rot.
     */
    private function duplicateNames(): void
    {
        DB::table('cities')
            ->selectRaw('country_id, LOWER(TRIM(name)) as normalised, COUNT(*) as anzahl')
            ->groupByRaw('country_id, LOWER(TRIM(name))')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $gruppe): void {
                $ids = DB::table('cities')
                    ->where('country_id', $gruppe->country_id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [$gruppe->normalised])
                    ->orderBy('id')
                    ->pluck('id');

                $this->add('namensdubletten', [
                    'name' => $gruppe->normalised,
                    'country_id' => $gruppe->country_id,
                    'ids' => $ids->all(),
                ]);
            });
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
