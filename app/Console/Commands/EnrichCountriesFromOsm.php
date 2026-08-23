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
        {--interval-ms= : Abstand zwischen zwei Nominatim-Anfragen in Millisekunden. NUR fuer Tests herabsetzen — die Policy verlangt 15000 fuer Massenlaeufe.}';

    protected $description = 'Ergaenzt fehlende OpenStreetMap-Referenzen auf Laendern (nur leere Felder)';

    /**
     * Wie deutlich der beste Treffer den zweitbesten schlagen muss, damit die Wahl
     * ohne Menschen faellt. 1,05 trennt echte Sieger (gemessen: 1,14 bis 1,26) von
     * Gleichstaenden (exakt 1,0), ohne knappe Faelle durchzuwinken.
     */
    private const CLEAR_WINNER_FACTOR = 1.05;

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

        foreach ($countries as $country) {
            $hits = $this->boundaryRelations($client, $country);

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
     * Die Grenzrelationen, die zu diesem Land gehoeren koennten.
     *
     * Ein Land ist in OSM eine Relation mit `boundary`-Kategorie. Alles andere — Staedte,
     * Strassen, Flughaefen gleichen Namens — faellt hier raus, sonst wuerde aus "Georgia"
     * schnell der US-Bundesstaat statt des Landes.
     *
     * Zwei Anlaeufe, weil die beiden Fehlerarten gegenlaeufig sind:
     *
     * 1. Mit Nominatims `featureType=country`. Gemessen am 2026-08-23: Mexico faellt
     *    damit von vier Treffern auf einen, Netherlands und Algeria von zwei auf einen.
     * 2. Findet der Filter nichts, noch einmal ohne ihn. Gebiete ohne eigene
     *    Land-Relation — Antarktis, abhaengige Territorien — verschwinden sonst ganz,
     *    und der erste Lauf hatte davon schon genug.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function boundaryRelations(NominatimClient $client, Country $country): Collection
    {
        $name = $country->english_name ?: $country->name;

        $hits = $this->relationsFor($client, $name, $country->code, 'country');

        return $hits->isNotEmpty()
            ? $hits
            : $this->relationsFor($client, $name, $country->code, null);
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
