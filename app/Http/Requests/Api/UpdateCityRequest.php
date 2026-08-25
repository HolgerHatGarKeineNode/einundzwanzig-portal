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

    /**
     * Zwei Abilities, zwei Fragen (Issue #30).
     *
     * `update` darf jeder angemeldete Nutzer — Anreichern ist ausdruecklich offen.
     * Sobald die Eingabe aber ein Identitaetsfeld WIRKLICH aendert (Name, Land,
     * Region, Einwohnerzahl, Stichjahr), gilt zusaetzlich `updateIdentity`.
     *
     * Der Fehlschlag ist ein 403 und kein stilles Verwerfen: eine Aenderung, die
     * scheinbar durchlaeuft und nichts tut, kostet den Aufrufer mehr als eine
     * abgelehnte — er merkt sie erst, wenn ihm jemand sagt, dass der Wert noch der
     * alte ist.
     */
    public function authorize(): bool
    {
        $city = $this->cityUnderEdit();

        if ($city === null || ! $this->user()->can('update', $city)) {
            return false;
        }

        return $city->identityChanges($this->all()) === []
            || $this->user()->can('updateIdentity', $city);
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
             * `unique` schuetzt hier vor dem versehentlichen Rename auf einen im selben
             * Land belegten Namen. Global ist der Name seit 2026-08-25 nicht mehr
             * eindeutig (Issue #33) — die Regel ist deshalb landesbezogen und laesst
             * sich mit `confirm_duplicate` aufheben, genau wie beim Anlegen.
             */
            'name' => ['sometimes', 'required', 'string', 'max:255', ...$this->uniqueCityName($city)],
            'confirm_duplicate' => ['sometimes', 'boolean'],
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
