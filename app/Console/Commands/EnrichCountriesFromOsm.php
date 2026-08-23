<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Services\Osm\NominatimClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Reichert Laender um ihre OpenStreetMap-Referenz an (Issue #12).
 *
 * Der Lauf ist langsam, und das ist Absicht: Nominatims Usage-Policy beschraenkt
 * Skripte, die regelmaessig oder laenger als einen Tag laufen, auf 4 Anfragen pro
 * Minute. `NominatimClient::forBulk()` haelt diese 15 Sekunden ein. 249 Laender
 * dauern damit rund eine Stunde.
 *
 * Daraus folgt der Rest des Entwurfs: nach jedem Land wird sofort gespeichert, damit
 * ein Abbruch nichts kostet und ein zweiter Aufruf dort weitermacht, wo der erste
 * aufhoerte. Es gibt bewusst kein --force: bestehende Werte zu ueberschreiben ist eine
 * andere Entscheidung als leere zu fuellen, und niemand hat bisher ueber veraltete
 * OSM-Daten geklagt.
 */
class EnrichCountriesFromOsm extends Command
{
    protected $signature = 'countries:enrich-from-osm
        {--limit=0 : Hoechstens so viele Laender bearbeiten (0 = alle)}
        {--code=* : Nur diese Laendercodes, z. B. --code=de --code=us}
        {--dry-run : Nur zeigen, was passieren wuerde}
        {--interval-ms= : Abstand zwischen zwei Nominatim-Anfragen in Millisekunden. NUR fuer Tests herabsetzen — die Policy verlangt 15000 fuer Massenlaeufe.}
        {--from-csv : Die OSM-Relation aus database/data/country-enrichment.csv nehmen statt sie zu suchen}';

    protected $description = 'Ergaenzt fehlende OpenStreetMap-Referenzen auf Laendern (nur leere Felder)';

    /**
     * Wie deutlich der beste Treffer den zweitbesten schlagen muss, damit die Wahl
     * ohne Menschen faellt. 1,05 trennt echte Sieger (gemessen: 1,14 bis 1,26) von
     * Gleichstaenden (exakt 1,0), ohne knappe Faelle durchzuwinken.
     */
    private const CLEAR_WINNER_FACTOR = 1.05;

    /**
     * Die ISO-Code-zu-OSM-Relation-Liste aus Issue #12.
     *
     * Beigesteuert von `RelativelyIrrelevant`, erzeugt aus der ISO-3166-Liste plus einer
     * Overpass-Abfrage nach `admin_level=2`-Relationen mit `ISO3166-1:alpha2`-Tag. Sie
     * loest das, was die Namenssuche prinzipiell nicht kann: abhaengige Gebiete ohne
     * eigene Land-Relation unter ihrem Namen — Puerto Rico, Hongkong, Réunion, Guam.
     *
     * Uebernommen wird daraus ausschliesslich die IDENTITAET (`osm_id`). Name, Adresse,
     * Koordinaten, wikidata und wikipedia holt danach `lookup()` bei Nominatim. Damit
     * pruefen sich die fremden Daten selbst: findet Nominatim zu der Relation nichts,
     * wird nichts geschrieben.
     *
     * Stichprobe vor der Uebernahme (2026-08-23, neun Laender quer durch die Gruppen):
     * osm_id, wikidata und wikipedia stimmten in allen neun Faellen exakt mit dem
     * ueberein, was Nominatim zu derselben Relation sagt.
     */
    private const CSV_SOURCE = 'database/data/country-enrichment.csv';

    public function handle(): int
    {
        /*
         * Bewusst selbst gebaut statt per Dependency Injection: die Container-Bindung
         * liefert die normale Instanz mit 1,1 Sekunden Abstand, und die waere fuer einen
         * Lauf ueber 249 Laender ein Policy-Verstoss. forBulk() haelt die 15 Sekunden ein,
         * die Nominatim fuer regelmaessige oder lang laufende Skripte verlangt.
         */
        $intervalMs = $this->option('interval-ms');
        $client = $intervalMs === null
            ? NominatimClient::forBulk()
            : new NominatimClient(minIntervalMs: (int) $intervalMs);

        $dryRun = (bool) $this->option('dry-run');
        $codes = array_map(mb_strtolower(...), (array) $this->option('code'));
        $limit = (int) $this->option('limit');

        $countries = Country::query()
            ->withoutOsmReference()
            ->when($codes !== [], fn ($query) => $query->whereIn(Country::raw('LOWER(code)'), $codes))
            ->orderBy('name')
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->get();

        if ($countries->isEmpty()) {
            $this->info('Nichts zu tun — alle betroffenen Laender haben bereits eine OSM-Referenz.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%d Laender, rund %d Minuten bei 15 Sekunden Abstand.%s',
            $countries->count(),
            (int) ceil($countries->count() * 15 / 60),
            $dryRun ? ' (Probelauf, es wird nichts geschrieben.)' : '',
        ));

        $filled = 0;
        $ambiguous = 0;
        $missing = 0;

        $csv = $this->option('from-csv') ? $this->csvByCode() : null;

        if ($csv !== null && $csv === []) {
            $this->error(self::CSV_SOURCE.' fehlt oder ist leer.');

            return self::FAILURE;
        }

        foreach ($countries as $country) {
            $hits = $csv !== null
                ? $this->relationFromCsv($client, $country, $csv)
                : $this->boundaryRelations($client, $country);

            if ($hits->isEmpty()) {
                $missing++;
                $this->warn("  {$country->code} {$country->name}: kein Treffer");

                continue;
            }

            $hit = $hits->count() === 1 ? $hits->first() : $this->clearWinner($hits);

            if ($hit === null) {
                /*
                 * Lieber nichts als das Falsche: die falsche Relation zu waehlen faellt
                 * niemandem auf, bis jemand der Karte nicht mehr traut.
                 */
                $ambiguous++;
                $this->warn("  {$country->code} {$country->name}: {$hits->count()} Grenzrelationen, kein klarer Vorsprung — uebersprungen");

                continue;
            }
            $changes = $this->emptyFieldsFrom($country, $hit);

            if ($changes === []) {
                continue;
            }

            $this->line("  {$country->code} {$country->name}: ".implode(', ', array_keys($changes)));

            if (! $dryRun) {
                // Sofort speichern, nicht am Ende: ein Abbruch nach vierzig Minuten
                // darf nicht vierzig Minuten Arbeit kosten.
                $country->forceFill($changes)->save();
            }

            $filled++;
        }

        $this->info(sprintf(
            'Fertig: %d ergaenzt, %d mehrdeutig, %d ohne Treffer (von %d).',
            $filled, $ambiguous, $missing, $countries->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Die OSM-Relation eines Landes laut CSV, angereichert ueber Nominatim.
     *
     * Rueckgabe als Collection mit hoechstens einem Element, damit der Aufrufer
     * denselben Pfad geht wie bei der Suche.
     *
     * @param  array<string, array<string, string>>  $csv
     * @return Collection<int, array<string, mixed>>
     */
    private function relationFromCsv(NominatimClient $client, Country $country, array $csv): Collection
    {
        $osmId = (int) ($csv[mb_strtolower($country->code)]['osm_id'] ?? 0);

        if ($osmId <= 0) {
            return collect();
        }

        $hit = $client->lookup('relation', $osmId);

        return $hit === null ? collect() : collect([$hit]);
    }

    /**
     * Die CSV als Zuordnung Laendercode (klein) zu Zeile.
     *
     * @return array<string, array<string, string>>
     */
    private function csvByCode(): array
    {
        $path = base_path(self::CSV_SOURCE);

        if (! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle, escape: '');
        $rows = [];

        try {
            while ($row = fgetcsv($handle, escape: '')) {
                $line = array_combine($header, $row);

                if (($line['code'] ?? '') !== '') {
                    $rows[mb_strtolower($line['code'])] = $line;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Die Grenzrelationen, die zu diesem Land gehoeren koennten.
     *
     * Ein Land ist in OSM eine Relation mit `boundary`-Kategorie. Alles andere — Staedte,
     * Strassen, Flughaefen gleichen Namens — faellt hier raus, sonst wuerde aus "Georgia"
     * schnell der US-Bundesstaat statt des Landes.
     *
     * Mehrere Anlaeufe, weil die Fehlerarten gegenlaeufig sind:
     *
     * 1. Mit Nominatims `featureType=country`. Gemessen am 2026-08-23: Mexico faellt
     *    damit von vier Treffern auf einen, Netherlands und Algeria von zwei auf einen.
     * 2. Findet der Filter nichts, noch einmal ohne ihn. Gebiete ohne eigene
     *    Land-Relation — Antarktis, abhaengige Territorien — verschwinden sonst ganz.
     * 3. Beides noch einmal mit ausgeschriebenem Namen, falls unsere Schreibweise
     *    kuerzt (siehe searchTerms()).
     *
     * Der erste Anlauf mit Treffern gewinnt; die Reihenfolge ist von praezise nach
     * grosszuegig sortiert.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function boundaryRelations(NominatimClient $client, Country $country): Collection
    {
        $name = $country->english_name ?: $country->name;

        foreach ($this->searchTerms($name) as $term) {
            foreach (['country', null] as $featureType) {
                $hits = $this->relationsFor($client, $term, $country->code, $featureType);

                if ($hits->isNotEmpty()) {
                    return $hits;
                }
            }
        }

        return collect();
    }

    /**
     * Die Schreibweisen, unter denen ein Land in OpenStreetMap zu finden sein kann.
     *
     * Unsere Laenderliste kuerzt, OpenStreetMap schreibt aus. Gemessen am 2026-08-23:
     *
     *   "Antigua & Barbuda"   0 Treffer   "Antigua and Barbuda"   1 (R536900)
     *   "Trinidad & Tobago"   0 Treffer   "Trinidad and Tobago"   1 (R555717)
     *   "St. Kitts & Nevis"   0 Treffer   "Saint Kitts and Nevis" 1 (R536899)
     *
     * Die Variante wird nur versucht, wenn sie sich vom Original unterscheidet — sonst
     * kostet jeder Lauf eine zweite Anfrage pro Land fuer nichts, und bei 15 Sekunden
     * Abstand ist das eine halbe Stunde.
     *
     * @return array<int, string>
     */
    private function searchTerms(string $name): array
    {
        $expanded = str_replace(
            [' & ', 'St. ', 'Ste. '],
            [' and ', 'Saint ', 'Sainte '],
            $name,
        );

        return $expanded === $name ? [$name] : [$name, $expanded];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function relationsFor(NominatimClient $client, string $name, string $code, ?string $featureType): Collection
    {
        return $client
            ->search($name, $code, limit: 10, featureType: $featureType)
            ->filter(fn (array $hit): bool => ($hit['osm_type'] ?? null) === 'relation'
                && ($hit['category'] ?? null) === 'boundary')
            ->values();
    }

    /**
     * Der eindeutig wichtigste Treffer, oder null bei zu knappem Vorsprung.
     *
     * Nominatims `importance` rangiert das Land ueber alles, was zufaellig so heisst.
     * Wo der Vorsprung deutlich ist, ist die Wahl sicher: Zypern kommt auf 0,7777
     * gegen 0,6152 fuer die britische Militaerbasis Akrotiri and Dhekelia.
     *
     * Wo er es nicht ist, wird nicht geraten. Die Niederlande lieferten zwei Relationen
     * mit exakt 0,8864 — das europaeische Nederland und das Koenigreich —, und welche
     * davon gemeint ist, kann ein Zahlenvergleich nicht entscheiden.
     *
     * @param  Collection<int, array<string, mixed>>  $hits
     * @return array<string, mixed>|null
     */
    private function clearWinner(Collection $hits): ?array
    {
        $ranked = $hits->sortByDesc(fn (array $hit): float => (float) ($hit['importance'] ?? 0))->values();
        $best = (float) ($ranked[0]['importance'] ?? 0);
        $second = (float) ($ranked[1]['importance'] ?? 0);

        if ($best <= 0.0 || $second <= 0.0) {
            return null;
        }

        return $best >= $second * self::CLEAR_WINNER_FACTOR ? $ranked[0] : null;
    }

    /**
     * Nur die Felder, die heute leer sind — mit dem Wert, den OSM dafuer kennt.
     *
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    private function emptyFieldsFrom(Country $country, array $hit): array
    {
        $candidates = [
            'osm_type' => $hit['osm_type'] ?? null,
            'osm_id' => $hit['osm_id'] ?? null,
            'osm_name' => $hit['osm_name'] ?? null,
            'osm_address' => $hit['osm_address'] ?? null,
            'osm_lat' => $hit['osm_lat'] ?? null,
            'osm_lon' => $hit['osm_lon'] ?? null,
            'wikidata' => $hit['wikidata'] ?? null,
            'wikipedia' => $hit['wikipedia'] ?? null,
            // Die Koordinatenspalten des Landes sind aelter als die osm_*-Spalten und bei
            // 235 von 249 Laendern leer; der Mittelpunkt der Grenzrelation ist ein
            // brauchbarer Wert dafuer.
            'latitude' => $hit['osm_lat'] ?? null,
            'longitude' => $hit['osm_lon'] ?? null,
        ];

        return collect($candidates)
            ->filter(fn ($value, string $field): bool => $value !== null && $country->{$field} === null)
            ->all();
    }
}
