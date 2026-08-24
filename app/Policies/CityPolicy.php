<?php

namespace App\Policies;

use App\Models\City;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

/**
 * Wer eine Stadt aendern darf — und die Antwort ist zweigeteilt (Issue #30).
 *
 * Eine Stadt ist kein Besitz. `created_by` sagt, wer sie zuerst eingetippt hat, nicht
 * wem sie gehoert; Wien gehoert niemandem. Das Portal hat das immer so gehandhabt und
 * jedem angemeldeten Nutzer das Bearbeiten erlaubt — die REST-API bestand dagegen auf
 * dem Ersteller. Genau diese Divergenz meldete Issue #30.
 *
 * Aufgeloest wird sie nicht mit einem Schalter, sondern mit zwei Abilities, weil an
 * einer Stadt zwei verschiedene Dinge haengen:
 *
 *  - {@see self::update()} — ANREICHERN. OSM-Referenz, Wikidata, Wikipedia,
 *    Koordinaten. Additiv, jederzeit korrigierbar, und genau die Arbeit, die
 *    Issue #30 ueberhaupt ausgeloest hat: jemand hat sechs US-Staedte mit
 *    OSM-Referenzen versehen, die er nicht angelegt hatte. Das soll moeglich bleiben.
 *  - {@see self::updateIdentity()} — die fuenf Felder, die eine Stadt zu DIESER Stadt
 *    machen und ueber ihre Aussenwirkung entscheiden: `name` (global eindeutig, traegt
 *    den eingefrorenen Slug), `country_id`, `region_id`, `population`,
 *    `population_date`. Die letzten beiden entscheiden zusammen mit
 *    `simplified_geojson` darueber, ob die Meetups dieser Stadt im BTC-Map-Export
 *    erscheinen. Ein geleertes Stichjahr laesst fremde Meetups aus einem Drittsystem
 *    verschwinden — ohne Fehler und ohne dass es jemandem auffaellt.
 *
 * Zwei benannte Abilities statt einer Feldstaffelung in einer Methode: dasselbe
 * Muster, das {@see MeetupPolicy} mit update()/manageLeaders()/appointLeader() schon
 * faehrt. Wer die Identitaet aendern darf, ohne Ersteller zu sein, traegt die Rolle
 * `city-steward` ({@see User::managesAllCities()}, vergeben per `cities:grant-steward`).
 */
class CityPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Sichtbarkeit der „Meine Staedte"-Detailansicht (`GET /api/my-cities/{city}`,
     * MCP `show-my-city`). Das ist KEINE Sichtbarkeitsgrenze fuer die Stadt selbst —
     * die ist ueber `GET /api/cities` fuer jeden abrufbar. Es ist die Frage „gehoert
     * dieser Eintrag in MEINE Liste", und die beantwortet weiterhin `created_by`.
     */
    public function view(User $user, City $city): bool
    {
        return $this->owns($user, $city);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Anreichern: OSM-Referenz, Wikidata, Wikipedia, Koordinaten.
     *
     * Jeder angemeldete Nutzer. Damit tut die API endlich das, was das Portal seit
     * jeher tut, statt das Gegenteil zu behaupten.
     */
    public function update(User $user, City $city): bool
    {
        return true;
    }

    /**
     * Die Identitaetsfelder aendern: Name, Land, Region, Einwohnerzahl, Stichjahr.
     *
     * Ersteller, `city-steward` oder Super-Admin. Wer nur anreichern darf, bekommt
     * beim Versuch einen 403 statt eines stillen Verwerfens — eine Aenderung, die
     * scheinbar durchlaeuft und nichts tut, ist teurer als eine abgelehnte.
     */
    public function updateIdentity(User $user, City $city): bool
    {
        return $this->owns($user, $city) || $user->managesAllCities();
    }
}
