<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * The validation rules for an OpenStreetMap place.
 *
 * All three event types carry the same six columns, so they carry the same rules. Written
 * once because a place that validates differently depending on which endpoint it arrived
 * through is a bug waiting to happen — and because the pairing rule below is easy to forget.
 */
trait ValidatesOsmPlace
{
    /**
     * The docblocks below are not decoration: Scramble turns them into the field
     * descriptions of the published OpenAPI document, so they address API consumers.
     * Notes for our own developers stay as `//` comments.
     *
     * @param  bool  $partial  true for PATCH, where an absent field means "leave it alone"
     * @return array<string, array<int, mixed>>
     */
    protected function osmPlaceRules(bool $partial = false): array
    {
        $prefix = $partial ? ['sometimes'] : [];

        // NOTE: osm_type and osm_id deliberately skip $prefix, even on PATCH. `sometimes`
        // drops the whole rule set when the field is absent, taking `required_with` with it —
        // and absent is exactly the case the pairing rule exists to catch. Adding it here
        // would let half a pair through unvalidated. `nullable` alone already lets a request
        // that mentions neither field pass untouched.
        return [
            /**
             * Which kind of OpenStreetMap object the place is: `node`, `way` or `relation`.
             *
             * Must be sent together with `osm_id` — the two only identify a place as a pair,
             * because ids are unique per type, not globally. Send neither to leave the event
             * without a map location; `location` alone is a perfectly valid answer.
             *
             * Look values up via Nominatim (`https://nominatim.openstreetmap.org/search`) and
             * mind its usage policy: max 1 request/second, and a real User-Agent is required.
             *
             * @example node
             */
            'osm_type' => ['nullable', 'required_with:osm_id', Rule::in(['node', 'way', 'relation'])],

            /**
             * The OpenStreetMap object id, unique only in combination with `osm_type`.
             *
             * Together they form the permanent link to the place:
             * `https://www.openstreetmap.org/{osm_type}/{osm_id}`.
             *
             * @example 240109189
             */
            'osm_id' => ['nullable', 'required_with:osm_type', 'integer', 'min:1'],

            /**
             * The name of the place as OpenStreetMap knows it, e.g. "Bürgerhaus Neumarkt".
             *
             * Stored as a copy so the event stays readable when OSM renames or removes the
             * object. Not a substitute for `location`, which is what the organiser wrote.
             *
             * @example VHS Lippstadt
             */
            'osm_name' => [...$prefix, 'nullable', 'string', 'max:255'],

            /**
             * The full address line OpenStreetMap returns for the place.
             *
             * @example VHS Lippstadt, Barthstraße 2, 59555 Lippstadt, Deutschland
             */
            'osm_address' => [...$prefix, 'nullable', 'string', 'max:255'],

            /**
             * Latitude in decimal degrees, -90 to 90. Stored with 7 decimals (~1 cm).
             *
             * @example 51.6739
             */
            'osm_lat' => [...$prefix, 'nullable', 'numeric', 'between:-90,90'],

            /**
             * Longitude in decimal degrees, -180 to 180. Stored with 7 decimals (~1 cm).
             *
             * @example 8.3444
             */
            'osm_lon' => [...$prefix, 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Die beiden Querverweise, die Nominatim mit `extratags=1` mitliefert.
     *
     * Eigene Methode statt Teil von osmPlaceRules(), weil die Events sie nicht fuehren:
     * ein Veranstaltungsort hat selten einen Wikipedia-Artikel, eine Stadt oder ein Land
     * fast immer. Wer sie braucht, holt sie sich dazu.
     *
     * @param  array<int, string>  $prefix  ['sometimes'] fuer PATCH, sonst leer
     * @return array<string, array<int, mixed>>
     */
    protected function osmReferenceRules(array $prefix = []): array
    {
        return [
            /**
             * Die Wikidata-Q-ID des Orts, z. B. `Q64`.
             *
             * @example Q64
             */
            'wikidata' => [...$prefix, 'nullable', 'string', 'max:32'],

            /**
             * Der Wikipedia-Verweis in OSM-Schreibweise: Sprachpraefix, Doppelpunkt, Titel.
             *
             * Kein fertiger Link — die aufgeloeste URL steht in der Antwort als
             * `wikipedia_url` daneben.
             *
             * @example de:Berlin
             */
            'wikipedia' => [...$prefix, 'nullable', 'string', 'max:255'],
        ];
    }
}
