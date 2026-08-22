<?php

namespace App\Http\Resources;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /**
     * GET /countries antwortet seit jeher mit einem nackten Array, nicht mit
     * {"data": [...]}. Das Wrapping hier abzuschalten ist der Unterschied zwischen
     * "neue Felder kommen dazu" und "jeder bestehende Client bricht".
     */
    public static $wrap = null;

    /**
     * Die vier Felder oben sind der bestehende Vertrag von GET /countries und behalten
     * Name, Typ und Wert — insbesondere wird `flag` weiter aus dem Laendercode gebildet,
     * damit vorhandene Clients dieselbe URL sehen wie vorher.
     *
     * Issue #12 schlaegt vor, das `->select('id','name','code')` im Controller ersatzlos
     * zu streichen, damit "die bereits vorhandenen, aber versteckten Felder mitfliessen".
     * Stattdessen steht hier eine ausdrueckliche Liste: was die API zusagt, soll man
     * lesen koennen, statt es aus dem Tabellenschema zu erschliessen. `english_name` und
     * `language_codes` bleiben deshalb vorerst draussen.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'flag' => asset('vendor/blade-flags/country-'.$this->code.'.svg'),

            /*
             * Neu (Issue #12). Alles null, solange das Land keine OSM-Referenz traegt —
             * heute der Normalfall, den der Anreicherungslauf nach und nach fuellt.
             */
            'osm_type' => $this->osm_type,
            'osm_id' => $this->osm_id,
            'osm_url' => $this->osm_url,
            'osm_name' => $this->osm_name,
            'wikidata' => $this->wikidata,
            'wikidata_url' => $this->wikidata_url,
            // OSM-Slug der Form "en:United States"; die aufgeloeste URL steht daneben.
            'wikipedia' => $this->wikipedia,
            'wikipedia_url' => $this->wikipedia_url,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
