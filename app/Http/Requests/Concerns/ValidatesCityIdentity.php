<?php

namespace App\Http\Requests\Concerns;

use App\Models\City;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Die beiden Regeln, an denen die Identitaet einer Stadt haengt: ihr Name und ihre Region.
 *
 * Beide werden von drei Schreibpfaden gebraucht (REST-API, MCP-Tool, Portal-Formular) und
 * waren bis Issue #30 nur im Portal umgesetzt. Eine Stadt, die je nach Eingang anders
 * validiert, ist genau die Art Unterschied, die niemand vermutet und jeder debuggt.
 */
trait ValidatesCityIdentity
{
    /**
     * Die Stadt, die gerade bearbeitet wird, falls sie nicht aus der Route kommt.
     *
     * Das MCP-Tool laeuft nicht durch den Router und muss sie darum selbst setzen.
     */
    protected ?City $cityUnderEdit = null;

    /**
     * Die Stadt, um die es geht — aus der Route oder ausdruecklich gesetzt.
     */
    protected function cityUnderEdit(): ?City
    {
        if ($this->cityUnderEdit instanceof City) {
            return $this->cityUnderEdit;
        }

        $fromRoute = $this->route('city');

        return $fromRoute instanceof City ? $fromRoute : null;
    }

    /**
     * `cities.name` ist auf DB-Ebene unique. Ohne diese Regel endet ein Rename auf
     * einen belegten Namen in einer UniqueConstraintViolationException — also 500
     * statt 422. Beim Anlegen faengt `City::createOrFindByName()` den Fall ab und
     * gibt die bestehende Stadt zurueck; beim Aendern gab es bisher nichts.
     */
    protected function uniqueCityName(?City $city = null): Unique
    {
        $rule = Rule::unique('cities', 'name');

        $city ??= $this->cityUnderEdit();

        return $city === null ? $rule : $rule->ignore($city->getKey());
    }

    /**
     * Die Region MUSS zum gewaehlten Land gehoeren.
     *
     * Ohne die `where`-Einschraenkung liesse sich jede beliebige Region-ID
     * unterschieben — eine deutsche Stadt bekaeme einen US-Bundesstaat. Das Portal
     * prueft das seit jeher (cities/edit), die API kannte das Feld gar nicht.
     *
     * Bei PATCH ist das massgebliche Land das mitgeschickte `country_id`; fehlt es,
     * gilt das der bestehenden Stadt. Genau deshalb steht der Wert hier als Parameter
     * und wird nicht in der Regel selbst nachgeschlagen.
     *
     * @param  array<int, string>  $prefix  ['sometimes'] fuer PATCH, sonst leer
     * @return array<int, mixed>
     */
    protected function regionRules(mixed $countryId, array $prefix = []): array
    {
        return [
            ...$prefix,
            'nullable',
            'integer',
            Rule::exists('regions', 'id')->where('country_id', $countryId),
        ];
    }
}
