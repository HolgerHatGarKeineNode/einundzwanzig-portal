<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Die Laender, fuer die dieses Portal ueberhaupt etwas anzubieten hat.
 *
 * ## Woher die Liste kommt — und warum sie nicht dasteht
 *
 * Der Betreiber hat die Regel am 2026-08-25 so formuliert: „Wir supporten eigentlich nur
 * die Laender, fuer welche wir Sprachen entwickelt haben und welche eine eigene Subdomain
 * fuer portal. bekommen haben."
 *
 * Die erste Haelfte ist im Repo belegbar und wird hier gerechnet, exakt so, wie es
 * `resources/views/livewire/language/selector.blade.php` fuer die SPRACHwahl tut:
 * Schnittmenge aus den vorhandenen `lang/*.json` und `config('lang-country.allowed')`.
 * Aus jedem verbleibenden `lang-country`-Paar wird die Landeshaelfte genommen.
 *
 * **Die zweite Haelfte laesst sich im Repo NICHT belegen.** Es gibt keine Konfiguration,
 * keine Migration und keinen Code, der die `portal.`-Subdomains auffuehrt — sie stehen im
 * DNS und in der Server-Konfiguration, nicht hier. Diese Klasse behauptet deshalb nicht,
 * sie abzubilden; sie bildet die Sprach-Haelfte ab und sagt es. Wer die Subdomains
 * gegenpruefen will, tut das ausserhalb dieses Repos.
 *
 * ## Warum berechnet statt aufgeschrieben
 *
 * Eine hartkodierte Liste waere am Tag ihrer Entstehung richtig und danach nie wieder.
 * Kommt eine `lang/fr.json` dazu, ist Frankreich sofort dabei — ohne dass jemand daran
 * denken muss, dass ausser der Uebersetzung auch noch eine Deploy-Zeile nachzuziehen
 * waere. Genau diese vergessene zweite Stelle ist der Grund, warum die 13 franzoesischen
 * Regionen auf Produktion existieren, aber ueber keine URL erreichbar waren.
 */
final class SupportedCountries
{
    /**
     * Kleingeschriebene ISO-3166-1-alpha-2-Codes, alphabetisch, ohne Dubletten.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return self::langCountryPairs()
            ->map(fn (string $pair): string => mb_strtolower(Str::after($pair, '-')))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Die `lang-country`-Paare hinter {@see self::codes()}, z. B. `de-AT`, `en-GB`.
     *
     * Getrennt oeffentlich, weil die Zwischenstufe die eigentliche Begruendung traegt:
     * `at` steht in der Liste wegen `de-AT`, nicht weil jemand Oesterreich einzeln
     * eingetragen haette.
     *
     * @return Collection<int, string>
     */
    public static function langCountryPairs(): Collection
    {
        $uebersetzt = collect(glob(lang_path('*.json')) ?: [])
            ->map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME))
            ->all();

        $erlaubt = collect(config('lang-country.allowed', []));

        return collect(config('lang-country.languages', []))
            ->only($uebersetzt)
            ->flatMap(fn (array $sprache): array => $sprache['countries'] ?? [])
            /*
             * `intersect` und nicht `filter(in_array)`: `allowed` ist die Freigabeliste,
             * `languages` die Angebotsliste. Ein Paar, das nur in einer von beiden steht,
             * ist keine Freigabe — der Sprachwaehler zieht dieselbe Schnittmenge.
             */
            ->intersect($erlaubt)
            ->unique()
            ->values();
    }
}
