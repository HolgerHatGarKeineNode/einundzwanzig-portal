<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Befuellt `regions` aus der ISO-3166-2-Liste in `database/data/regions.csv`.
 *
 * Die CSV stammt aus `squirephp/regions-en` (dort `resources/data.csv`) und liegt bewusst
 * als Kopie im Repo: das Paket ist eine reine Datenquelle unter `require-dev` und fehlt in
 * einem Produktions-Deploy mit `--no-dev`. `--refresh-source` zieht die aktuelle Fassung
 * aus dem Paket nach — das laeuft nur auf einer Entwicklungsmaschine.
 *
 * Gefiltert wird auf ausdruecklich freigeschaltete Laender: Regionen anzulegen, die
 * niemand pflegt, erzeugt nur URLs, hinter denen nie etwas steht.
 */
class ImportRegions extends Command
{
    protected $signature = 'regions:import
        {--country=* : Laendercodes, z. B. --country=us --country=de. Ohne Angabe: nur us}
        {--refresh-source : Die CSV vorher aus vendor/squirephp/regions-en aktualisieren}';

    protected $description = 'Importiert ISO-3166-2-Regionen (Verwaltungsebene 1) fuer die angegebenen Laender';

    private const SOURCE = 'database/data/regions.csv';

    private const PACKAGE_SOURCE = 'vendor/squirephp/regions-en/resources/data.csv';

    public function handle(): int
    {
        if ($this->option('refresh-source') && ! $this->refreshSource()) {
            return self::FAILURE;
        }

        $path = base_path(self::SOURCE);

        if (! is_readable($path)) {
            $this->error(self::SOURCE.' fehlt oder ist nicht lesbar.');

            return self::FAILURE;
        }

        $wanted = array_values(array_unique(
            array_map(mb_strtolower(...), $this->option('country') ?: ['us'])
        ));

        /*
         * Case-insensitiv, weil `countries.code` je nach Datenbestand gross- oder
         * kleingeschrieben ist (Produktion: "us", lokale Testdaten: "US") und SQLite
         * anders als MySQL exakt vergleicht.
         */
        $matches = Country::query()
            ->whereIn(Country::raw('LOWER(code)'), $wanted)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Country $country) => mb_strtolower($country->code));

        $countries = collect();

        foreach ($wanted as $code) {
            /** @var Collection<int, Country> $found */
            $found = $matches->get($code, collect());

            if ($found->isEmpty()) {
                $this->warn("Land '{$code}' existiert nicht in `countries` — uebersprungen.");

                continue;
            }

            /*
             * MEHRDEUTIGER LAENDERCODE — und warum das ein Abbruch ist, kein Vorrang.
             *
             * Der Vergleich oben ist case-insensitiv, `countries.code` aber nicht
             * eindeutig: im lokalen Bestand vom 2026-08-25 stehen zwei Zeilen "CH"
             * (id 8 und 10) und neben "US" (id 7) eine Zeile "us", die "Deutschland"
             * heisst (id 9). Bis hierher entschied `keyBy()` das still — es behaelt den
             * LETZTEN Treffer. Der Import haette damit alle 51 US-Regionen an die
             * Zeile id 9 gehaengt, waehrend die drei US-Staedte an id 7 haengen: 51 neue
             * Regionszeilen, die keine Stadt je erreicht, und kein Wort darueber.
             *
             * Welche der beiden Zeilen die richtige ist, kann dieses Kommando nicht
             * wissen — das ist eine Entscheidung ueber Stammdaten. Also fail-closed:
             * dieses Land wird uebersprungen, die uebrigen laufen weiter, und die
             * Meldung nennt die ids, damit die Entscheidung ueberhaupt treffbar ist.
             */
            if ($found->count() > 1) {
                $liste = $found
                    ->map(fn (Country $country): string => "#{$country->id} {$country->code} \"{$country->name}\"")
                    ->join(', ');

                $this->warn("Laendercode '{$code}' ist mehrdeutig — {$found->count()} Zeilen in `countries` ({$liste}). Uebersprungen: welche Zeile die Regionen tragen soll, ist eine Datenentscheidung.");

                continue;
            }

            $countries->put($code, $found->first());
        }

        if ($countries->isEmpty()) {
            $this->error('Kein bekanntes Land angegeben, nichts zu tun.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        foreach ($this->rows($path) as [$code, $countryCode, $name]) {
            if (! $country = $countries->get($countryCode)) {
                continue;
            }

            $region = Region::query()->updateOrCreate(
                ['country_id' => $country->id, 'code' => $code],
                ['name' => $name, 'slug' => Str::slug($name)],
            );

            $region->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Regionen importiert: {$created} neu, {$updated} aktualisiert ({$countries->keys()->implode(', ')}).");

        return self::SUCCESS;
    }

    /**
     * Liefert je Zeile [regionaler Code ohne Laenderpraefix, Laendercode, Name].
     *
     * Die Quelle fuehrt den Code als "us-in"; gespeichert wird nur "in", weil das Land
     * bereits ueber `country_id` haengt und "in" das URL-Segment ist.
     *
     * @return \Generator<int, array{string, string, string}>
     */
    private function rows(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        fgetcsv($handle, escape: '');

        try {
            while ($row = fgetcsv($handle, escape: '')) {
                [, $isoCode, $countryCode, $name] = $row;

                yield [Str::after($isoCode, '-'), mb_strtolower($countryCode), $name];
            }
        } finally {
            fclose($handle);
        }
    }

    private function refreshSource(): bool
    {
        $package = base_path(self::PACKAGE_SOURCE);

        if (! is_readable($package)) {
            $this->error(self::PACKAGE_SOURCE.' fehlt — laeuft nur mit installierten dev-Abhaengigkeiten.');

            return false;
        }

        copy($package, base_path(self::SOURCE));
        $this->line('Quelle aus dem Paket aktualisiert.');

        return true;
    }
}
