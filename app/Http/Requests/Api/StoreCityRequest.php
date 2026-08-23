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
             * Kein `unique` auf `name`: `City::createOrFindByName()` gibt eine bereits
             * bestehende Stadt mit 200 zurueck, statt sie als Fehler abzuweisen. Das ist
             * der dokumentierte Vertrag dieses Endpunkts und bleibt so.
             */
            'region_id' => $this->regionRules(countryId: $this->input('country_id')),
            'population' => ['nullable', 'integer', 'min:0'],
            'population_date' => ['nullable', 'string', 'max:255'],
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
        $this->merge(self::coordinatesFromOsm($this->all()));
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
