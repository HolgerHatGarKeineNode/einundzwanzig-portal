<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesCityIdentity;
use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCityRequest extends FormRequest
{
    use ValidatesCityIdentity;
    use ValidatesOsmPlace;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('city'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $city = $this->cityUnderEdit();

        return [
            'country_id' => ['sometimes', 'required', 'integer', 'exists:countries,id'],
            /*
             * `unique` ist hier kein Komfort, sondern der Unterschied zwischen 422 und 500:
             * `cities.name` ist auf DB-Ebene unique, und ohne diese Regel endete ein
             * Rename auf einen belegten Namen in einer UniqueConstraintViolationException.
             * `City::createOrFindByName()` faengt das nur beim ANLEGEN ab, nicht hier.
             * Das Portal validiert es seit jeher (cities/edit); die API tat es nicht.
             */
            'name' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueCityName($city)],
            'region_id' => $this->regionRules(
                countryId: $this->input('country_id', $city?->country_id),
                prefix: ['sometimes'],
            ),
            'longitude' => ['sometimes', 'required', 'numeric'],
            'latitude' => ['sometimes', 'required', 'numeric'],
            'population' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'population_date' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(partial: true),
        ] + $this->osmReferenceRules(['sometimes']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_id.exists' => __('Das angegebene Land existiert nicht.'),
            'region_id.exists' => __('Die angegebene Region gehoert nicht zu diesem Land.'),
            'name.unique' => __('Eine Stadt mit diesem Namen existiert bereits.'),
        ];
    }

    /**
     * Die Stadt, um die es geht — als Model, nicht als Route-Parameter.
     *
     * Das MCP-Tool validiert gegen diese Regeln, laeuft aber nicht durch den Router.
     * Ohne diesen Umweg waere `cityUnderEdit()` dort null, die `unique`-Regel wuerde
     * die Stadt gegen sich selbst pruefen und jedes Speichern ohne Namensaenderung
     * schluege fehl.
     */
    public function withCity(?City $city): static
    {
        $this->cityUnderEdit = $city;

        return $this;
    }

    /**
     * Ein Request fuer denselben Vorgang ausserhalb des Router-Lebenszyklus.
     *
     * `rules()` liest zwei Dinge aus dem Request selbst: die Stadt (fuer `unique`, damit
     * sie sich ihren eigenen Namen nachsieht) und ein mitgeschicktes `country_id` (fuer
     * die Regionsregel). Ein blankes `new UpdateCityRequest` hat weder das eine noch das
     * andere — es wuerde jedes Speichern ohne Namensaenderung mit "Name bereits vergeben"
     * abweisen und die Region gegen das falsche Land pruefen.
     *
     * @param  array<string, mixed>  $input
     */
    public static function forCity(City $city, array $input = []): static
    {
        return (new static)->withCity($city)->replace($input);
    }
}
