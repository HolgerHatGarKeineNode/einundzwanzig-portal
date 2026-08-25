<?php

namespace App\Console\Commands\Database;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Observers\ApiChangeObserver;
use App\Policies\CityPolicy;
use App\Services\Osm\NominatimClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Fuellt `cities.region_id` fuer Bestandsstaedte — aber nur dort, wo die Zuordnung
 * eindeutig aus der bereits gespeicherten OSM-Adresse hervorgeht.
 *
 * ## Warum das ueberhaupt noetig ist
 *
 * `regions:import` legt die Verwaltungsebene 1 an, aber keine einzige Stadt zeigt
 * darauf. Ohne Rueckbefuellung entstehen Regionszeilen, die niemand sieht: die
 * Regionsrouten (`meetups.index-region`, `cities.index-region`) filtern auf
 * `cities.region_id` und liefern dann ueberall eine leere Liste — eine Seite, die
 * aussieht wie "hier gibt es nichts", obwohl es nur die Zuordnung nicht gibt.
 *
 * ## Warum ohne Netz
 *
 * Die Quelle ist `cities.osm_address` — die Adresszeile, die Nominatim beim Anlegen
 * geliefert hat ({@see NominatimClient}). Sie traegt die Region als
 * eigenes, komma-getrenntes Glied ("Muenchen, Bayern, Deutschland"). Damit ist die
 * Zuordnung ein Textabgleich gegen `regions.name`/`regions.code` und braucht keine
 * einzige Anfrage nach draussen. Ein Reverse-Geocoding fuer Staedte OHNE
 * `osm_address` waere der naechste Schritt — er kostet eine Anfrage pro Stadt unter
 * der Nominatim-Bulk-Bremse und ist deshalb bewusst NICHT Teil dieses Kommandos.
 *
 * ## Warum lieber zu wenig als zu viel
 *
 * `region_id` ist eines der fuenf Identitaetsfelder hinter
 * {@see CityPolicy::updateIdentity()}. Wer kein Steward-Recht hat, kann eine falsche
 * Zuordnung nicht selbst korrigieren — ein geratener Wert bleibt also stehen. Deshalb
 * gilt: mehr als ein Regionstreffer in derselben Adresse, oder ein Suchbegriff, der auf
 * zwei Regionen desselben Landes passt, fuehrt zu KEINER Zuordnung. Diese Faelle werden
 * gezaehlt und ausgegeben, statt sie aufzuloesen.
 *
 * ## Warum an der Policy vorbei
 *
 * Das Kommando schreibt ein Identitaetsfeld ohne Autorisierungspruefung — bewusst. Es
 * laeuft in der Konsole, hat keinen angemeldeten Nutzer, den es fragen koennte, und
 * korrigiert Bestandsdaten, statt eine Nutzereingabe entgegenzunehmen. Die Policy
 * schuetzt den Weg ueber Formular, REST-API und MCP-Tool; dieser Weg ist der
 * Betreiberweg. `--dry-run` ist die Gegenprobe, die das ersetzt.
 *
 * Der Schreibvorgang laeuft ueber `save()` und damit durch
 * {@see ApiChangeObserver} — jede Zuordnung erscheint als `updated` im
 * oeffentlichen Aenderungs-Feed. Das ist gewollt: `CityResource` gibt `region` aus, ein
 * Konsument saehe die Aenderung sonst nie.
 */
#[Signature('cities:backfill-regions
    {--country=* : Nur diese Laendercodes, z. B. --country=de --country=us. Ohne Angabe: alle}
    {--dry-run : Nur zeigen, was passieren wuerde — schreibt nichts}')]
#[Description('Fuellt cities.region_id aus der gespeicherten OSM-Adresse, wo die Zuordnung eindeutig ist.')]
class BackfillCityRegions extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $wanted = array_values(array_unique(
            array_map(mb_strtolower(...), $this->option('country'))
        ));

        $cities = City::query()
            ->with('country')
            ->whereNull('region_id')
            ->when($wanted !== [], fn ($query) => $query->whereHas(
                'country',
                fn ($query) => $query->whereIn(Country::raw('LOWER(code)'), $wanted),
            ))
            ->orderBy('id')
            ->get();

        if ($cities->isEmpty()) {
            $this->info('Keine Stadt ohne Region gefunden — nichts zu tun.');

            return self::SUCCESS;
        }

        $lookup = $this->lookupByCountry($cities->pluck('country_id')->unique()->all());

        $zugeordnet = 0;
        $mehrdeutig = 0;
        $ohneAdresse = 0;
        $ohneTreffer = 0;
        $ohneRegionen = 0;

        /** @var array<int, array{0: int, 1: string, 2: string, 3: string}> $zeilen */
        $zeilen = [];

        foreach ($cities as $city) {
            $regions = $lookup[$city->country_id] ?? [];

            if ($regions === []) {
                $ohneRegionen++;

                continue;
            }

            $address = trim((string) $city->osm_address);

            if ($address === '') {
                $ohneAdresse++;

                continue;
            }

            ['region' => $region, 'ambiguous' => $ambiguous] = $this->match($address, $regions);

            if ($ambiguous) {
                $mehrdeutig++;
                $zeilen[] = [$city->id, $city->name, '—', 'mehrdeutig, bleibt leer'];

                continue;
            }

            if ($region === null) {
                $ohneTreffer++;

                continue;
            }

            if (! $dryRun) {
                $city->region()->associate($region);
                $city->save();
            }

            $zugeordnet++;
            $zeilen[] = [$city->id, $city->name, $region->isoCode(), $dryRun ? 'wuerde zugeordnet' : 'zugeordnet'];
        }

        if ($zeilen !== []) {
            $this->table(['id', 'Stadt', 'Region', 'Ergebnis'], $zeilen);
        }

        if ($dryRun) {
            $this->warn('--dry-run: nichts geschrieben.');
        }

        $this->info(sprintf(
            'Staedte ohne Region geprueft: %d — zugeordnet %d, mehrdeutig %d, ohne OSM-Adresse %d, ohne Treffer in der Adresse %d, Land ohne Regionen %d.',
            $cities->count(),
            $zugeordnet,
            $mehrdeutig,
            $ohneAdresse,
            $ohneTreffer,
            $ohneRegionen,
        ));

        return self::SUCCESS;
    }

    /**
     * Je Land ein Suchbegriff-Verzeichnis: normalisierter Name ODER Code → Region.
     *
     * `null` als Wert heisst: dieser Begriff passt im selben Land auf mehr als eine
     * Region und darf deshalb nie zuordnen. Der Fall ist selten, aber real — ein
     * zweistelliger Regionscode kann der Anfang eines Regionsnamens sein, und ohne
     * diese Markierung entschiede die Ladereihenfolge.
     *
     * @param  array<int, int>  $countryIds
     * @return array<int, array<string, Region|null>>
     */
    private function lookupByCountry(array $countryIds): array
    {
        $lookup = [];

        foreach (Region::query()->whereIn('country_id', $countryIds)->orderBy('id')->get() as $region) {
            foreach ([$region->name, $region->code] as $begriff) {
                $key = $this->normalize((string) $begriff);

                if ($key === '') {
                    continue;
                }

                if (! array_key_exists($key, $lookup[$region->country_id] ?? [])) {
                    $lookup[$region->country_id][$key] = $region;

                    continue;
                }

                $vorhanden = $lookup[$region->country_id][$key];

                if ($vorhanden === null || $vorhanden->getKey() !== $region->getKey()) {
                    $lookup[$region->country_id][$key] = null;
                }
            }
        }

        return $lookup;
    }

    /**
     * Die eine Region, die in dieser Adresse steht — oder die Feststellung, dass es
     * nicht die eine ist.
     *
     * Zerlegt wird am Komma, weil die Nominatim-Adresszeile genau so aufgebaut ist. Ein
     * Teilstring-Vergleich waere hier falsch: "Bremen" steckt in "Bremerhaven", und
     * "Hessen" in "Hessisch Oldendorf". Nur ein vollstaendiges Glied zaehlt.
     *
     * @param  array<string, Region|null>  $regions
     * @return array{region: Region|null, ambiguous: bool}
     */
    private function match(string $address, array $regions): array
    {
        /** @var array<int, Region> $treffer */
        $treffer = [];
        $ambiguous = false;

        foreach (explode(',', $address) as $glied) {
            $key = $this->normalize($glied);

            if ($key === '' || ! array_key_exists($key, $regions)) {
                continue;
            }

            if ($regions[$key] === null) {
                $ambiguous = true;

                continue;
            }

            $treffer[$regions[$key]->getKey()] = $regions[$key];
        }

        if ($ambiguous || count($treffer) > 1) {
            return ['region' => null, 'ambiguous' => true];
        }

        return ['region' => count($treffer) === 1 ? reset($treffer) : null, 'ambiguous' => false];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
