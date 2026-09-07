<?php

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            /*
             * Issue #30: `region_id` und `population_date` sind seit jeher Spalten und im
             * Portal editierbar, standen aber in keiner Antwort. Zwei Folgen, beide
             * unsichtbar: ein API-Konsument konnte nicht sehen, was er gerade gesetzt
             * hatte, und das Aenderungs-Log (`api_changes`) traegt dieses Resource als
             * Payload — die beiden Felder waren daraus nicht rekonstruierbar. Additiv,
             * also bricht kein bestehender Konsument.
             */
            'region_id' => $this->region_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'population' => $this->population,
            /*
             * Kein Datum, sondern die Jahresangabe der Quelle ("2024", "2011-05-09").
             * `BtcMapCommunityController` entscheidet mit ihr ueber die Sichtbarkeit
             * eines Meetups im BTC-Map-Export — ein Feld, dessen Leeren woanders
             * Eintraege verschwinden laesst, gehoert in die Historie.
             */
            'population_date' => $this->population_date,
            /*
             * Issue #11: die OSM-Referenz. `osm_url` ist berechnet, nicht gespeichert —
             * `osm_type` und `osm_id` sind die Wahrheit, die URL nur ihre Lesart.
             * Alle Felder sind null, solange die Stadt keine Referenz traegt; das ist
             * der Normalfall fuer Bestandsdaten und kein Fehler.
             */
            'osm_type' => $this->osm_type,
            'osm_id' => $this->osm_id,
            'osm_url' => $this->osm_url,
            'osm_name' => $this->osm_name,
            'osm_address' => $this->osm_address,
            'osm_lat' => $this->osm_lat,
            'osm_lon' => $this->osm_lon,
            'wikidata' => $this->wikidata,
            'wikidata_url' => $this->wikidata_url,
            // OSM-Slug der Form "de:Berlin"; die aufgeloeste URL steht daneben.
            'wikipedia' => $this->wikipedia,
            'wikipedia_url' => $this->wikipedia_url,
            'created_by' => $this->created_by,
            /**
             * DEPRECATED (follow-up to #125): when this city was first written, UTC, in
             * Carbon's default JSON form — `2026-01-02T03:04:05.000000Z`, microseconds
             * and a `Z` suffix. Read `created_at_iso` below instead, which is the form
             * the rest of the API uses.
             *
             * Kept unchanged, byte for byte, until the consumers of these endpoints and
             * of the MCP tools have moved over — dropping it is a breaking change and
             * belongs to a coordinated client release, not to a later edit here. Both
             * fields describe the same instant, so a client can migrate one at a time.
             */
            'created_at' => $this->created_at,
            /**
             * The zone-marked replacement for `created_at` (follow-up to #125):
             * `2026-01-02T03:04:05+00:00` — ISO 8601 with a numeric OFFSET, not the `Z`
             * shorthand and without microseconds. Identical format and identical
             * `<field>_iso` naming to the twins {@see MeetupEventResource} carries since
             * issues #85 and #125, so a client reading more than one resource of this
             * API parses ONE form.
             *
             * This is the CONVERT case, not the reinterpret one: `created_at` is a
             * `datetime` cast, so the value arrives as an App\Support\Carbon that
             * already knows its zone, and `->setTimezone('UTC')` moves that known
             * instant to UTC. App\Support\Carbon extends CarbonImmutable, so the
             * conversion returns a new instance and cannot move the deprecated field.
             *
             * A no-op in value today (config('app.timezone') is UTC, and SetTimezone
             * runs on the web middleware group only — never on an API or MCP request).
             * Spelling the conversion out makes `+00:00` a promise of this resource
             * instead of a side effect of that configuration.
             *
             * `?->` because the column is nullable (`timestamps()` writes it nullable) —
             * a row written with timestamps disabled has no value, and a resource must
             * not fatal on one. Always present, and null in that case — never absent.
             */
            'created_at_iso' => $this->created_at?->setTimezone('UTC')->toIso8601String(),
            /**
             * DEPRECATED (follow-up to #125): when this city was last written, as
             * `2026-02-03T04:05:06.000000Z`. Read `updated_at_iso`. Kept unchanged, byte
             * for byte, on the same terms as `created_at` above.
             */
            'updated_at' => $this->updated_at,
            /** The zone-marked replacement for `updated_at` (follow-up to #125), on the same terms as `created_at_iso`. */
            'updated_at_iso' => $this->updated_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
