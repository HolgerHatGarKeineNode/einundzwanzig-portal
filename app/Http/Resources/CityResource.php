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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
