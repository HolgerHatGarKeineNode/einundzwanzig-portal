<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesCityIdentity;
use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    use ValidatesCityIdentity;
    use ValidatesOsmPlace;

    public function authorize(): bool
    {
        return $this->user()->can('create', City::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $prefix = [];

        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            /*
             * Gelockert von `required` auf `required_without:osm_lat` bzw. `osm_lon`:
             * eine Stadt darf aus Name, Land und einem OSM-Ort entstehen, dessen
             * Koordinaten mitkommen (Wunsch aus Issue #11). prepareForValidation()
             * uebernimmt sie dann in latitude/longitude — die Spalten sind in der
             * Datenbank NOT NULL, und daran aendert dieser Plan bewusst nichts.
             *
             * Fuer jeden bestehenden Aufrufer, der Koordinaten schickt, aendert sich
             * nichts: eine Lockerung bricht keinen Vertrag.
             */
            'longitude' => ['required_without:osm_lon', 'numeric'],
            'latitude' => ['required_without:osm_lat', 'numeric'],
            /*
             * Kein `unique` auf `name` — aber aus einem anderen Grund als frueher.
             *
             * Bis 2026-08-25 stand hier: `createOrFindByName()` gebe die bestehende
             * Stadt mit 200 zurueck. Genau das war der Fehler aus Issue #33 — sie gab
             * eine gleichnamige Stadt aus einem ANDEREN Land zurueck. Die Entscheidung
             * trifft jetzt `City::resolveOrCreate()`, und sie braucht dafuer mehr, als
             * eine Validierungsregel sehen kann: Land, OSM-Referenz und die Frage, ob
             * eine Neuanlage bestaetigt wurde. Eine `unique`-Regel haette hier nur eine
             * Haelfte davon geprueft und die andere verdeckt.
             */
            'region_id' => $this->regionRules(countryId: $this->input('country_id')),
            'population' => ['nullable', 'integer', 'min:0'],
            'population_date' => ['nullable', 'string', 'max:255'],
            /*
             * Die ausdrueckliche Bestaetigung, dass hier bewusst ein weiterer Ort
             * gleichen Namens entsteht. Ohne sie — und ohne OSM-Referenz — weist
             * `resolveOrCreate()` die Neuanlage neben einem gleichnamigen Ort mit 422 ab.
             */
            'confirm_duplicate' => ['sometimes', 'boolean'],
            ...$this->osmPlaceRules(),
        ] + $this->osmReferenceRules($prefix);
    }

    /**
     * Koordinaten aus dem OSM-Ort uebernehmen, wenn keine eigenen mitkamen.
     *
     * Vor der Validierung, damit `required_without` und die NOT-NULL-Spalten denselben
     * Wert sehen. Eigene Angaben gewinnen immer — wer Koordinaten schickt, hat einen
     * Grund dafuer.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(self::normalise($this->all()));
    }

    /**
     * Name trimmen und Koordinaten aus dem OSM-Ort uebernehmen.
     *
     * Der Trim ist keine Kosmetik: 12 der 305 Staedte in Produktion tragen ein
     * nachgestelltes Leerzeichen, und `'Offenburg '` steht dort seit 2023 neben
     * `'Offenburg'` — zwei Datensaetze fuer denselben Ort, weil ein unsichtbares Zeichen
     * sie fuer die Suche unterscheidbar machte. Wer das nicht beim Schreiben abfaengt,
     * sammelt es fuer immer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalise(array $data): array
    {
        return [
            ...self::coordinatesFromOsm($data),
            ...(isset($data['name']) && is_string($data['name']) ? ['name' => trim($data['name'])] : []),
        ];
    }

    /**
     * Dieselbe Uebernahme fuer Aufrufer ausserhalb des FormRequest-Lebenszyklus.
     *
     * Das MCP-Tool validiert gegen diese Regeln, laeuft aber nicht durch
     * prepareForValidation() — ohne diese Methode haette derselbe Aufruf ueber MCP
     * andere Anforderungen als ueber die API, und das ist genau die Art Unterschied,
     * die niemand vermutet und jeder debuggt.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function coordinatesFromOsm(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? $data['osm_lat'] ?? null,
            'longitude' => $data['longitude'] ?? $data['osm_lon'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_id.exists' => __('Das angegebene Land existiert nicht.'),
            'region_id.exists' => __('Die angegebene Region gehoert nicht zu diesem Land.'),
        ];
    }
}
