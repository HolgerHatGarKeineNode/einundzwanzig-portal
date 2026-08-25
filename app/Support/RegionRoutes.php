<?php

namespace App\Support;

use App\Console\Commands\ImportRegions;

/**
 * Welche Listen-Routen es in einer Landes- und einer Regionsvariante gibt.
 *
 * Es sind genau drei: Meetups, Karte und Staedte. Kurse, Referenten, Services und alle
 * Auth-Ziele haben keine Regionsvariante, und das ist Absicht — ein Regionscode, den keine
 * Route kennt, antwortet mit 404 statt mit einer leeren Liste.
 *
 * Die Zuordnung stand bis 2026-08-24 als private Konstante im Regionswaehler. Sie liegt
 * jetzt hier, weil ein zweiter Aufrufer dazugekommen ist: der LAENDERwaehler musste
 * lernen, die Region beim Wechsel fallen zu lassen. Er merkt sich beim Rendern alle
 * Routenparameter und gab sie unveraendert weiter — auf `/us/in/meetups` fuehrte ein
 * Wechsel nach Deutschland damit auf `/de/in/meetups`, und das ist ein 404: Indiana ist
 * kein deutsches Bundesland. Eine Region gehoert zu genau einem Land, also verlaesst man
 * sie mit dem Land.
 *
 * Kopieren waere die schlechtere Antwort gewesen: zwei Waehler mit derselben Liste laufen
 * auseinander, sobald eine vierte Regionsroute dazukommt, und der Fehler zeigt sich dann
 * nur in einem der beiden.
 */
class RegionRoutes
{
    /**
     * Was als Regionssegment in einer URL stehen darf.
     *
     * Ein bis drei alphanumerische Kleinbuchstaben/Ziffern — der ISO-3166-2-Suffix ohne
     * Landespraefix, so wie {@see ImportRegions} ihn ablegt. Steht
     * hier und nicht dreimal in `routes/web.php`, damit die drei Regionsrouten nicht
     * auseinanderlaufen koennen: eine Route mit engerem Muster antwortet auf denselben
     * Regionscode mit 404, waehrend die anderen beiden ihn bedienen — ein Unterschied,
     * den niemand sucht, weil die drei Zeilen nebeneinander stehen und gleich aussehen.
     */
    public const SEGMENT_PATTERN = '[a-z0-9]{1,3}';

    /**
     * Routenname → [Landesvariante, Regionsvariante].
     *
     * Beide Richtungen sind eingetragen, damit ein Aufrufer nicht wissen muss, ob er
     * gerade auf der Landes- oder der Regionsseite steht.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ROUTES = [
        'meetups.index' => ['meetups.index', 'meetups.index-region'],
        'meetups.index-region' => ['meetups.index', 'meetups.index-region'],
        'meetups.map' => ['meetups.map', 'meetups.map-region'],
        'meetups.map-region' => ['meetups.map', 'meetups.map-region'],
        'cities.index' => ['cities.index', 'cities.index-region'],
        'cities.index-region' => ['cities.index', 'cities.index-region'],
    ];

    /**
     * Das Routenpaar zu einem Namen, oder null, wenn die Route keine Regionsvariante hat.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function pair(string $route): ?array
    {
        return self::ROUTES[$route] ?? null;
    }

    /**
     * Die Variante OHNE Regionssegment — der Weg zurueck auf die Landesansicht.
     *
     * Gibt null zurueck, wenn die Route gar kein Regionspaar hat. Ein Aufrufer soll dann
     * nichts umbiegen, statt zu raten: `null` heisst „diese Route geht mich nichts an",
     * nicht „nimm die erste, die passt".
     */
    public static function plain(string $route): ?string
    {
        return self::pair($route)[0] ?? null;
    }

    /**
     * Die Variante MIT Regionssegment.
     */
    public static function withRegion(string $route): ?string
    {
        return self::pair($route)[1] ?? null;
    }

    /**
     * Kennt diese Route ueberhaupt eine Regionsvariante?
     */
    public static function supports(string $route): bool
    {
        return isset(self::ROUTES[$route]);
    }
}
