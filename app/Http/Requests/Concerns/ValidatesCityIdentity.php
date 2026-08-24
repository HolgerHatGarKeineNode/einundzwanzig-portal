<?php

namespace App\Http\Requests\Concerns;

use App\Models\City;
use Illuminate\Validation\Rule;

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
     * Der Name darf im selben Land nicht versehentlich doppelt vergeben werden.
     *
     * Bis 2026-08-25 war `cities.name` auf DB-Ebene global unique, und diese Regel war
     * der Unterschied zwischen 422 und 500. Der Index ist gefallen (Issue #33: acht
     * Gemeinden namens Neuenkirchen in Niedersachsen), die Regel bleibt — aber in
     * anderer Rolle und mit anderem Zuschnitt:
     *
     * - **Landesbezogen statt global.** Paris in Frankreich und Paris in Texas sind kein
     *   Konflikt, und die Regel darf keinen daraus machen.
     * - **`confirm_duplicate` hebt sie auf.** Ein zweites Georgetown im selben Land ist
     *   erlaubt; es muss nur eine Entscheidung sein. Genau dieselbe Bedingung gilt beim
     *   Anlegen in `City::resolveOrCreate()` — waere sie hier strenger, koennte man eine
     *   Stadt anlegen, aber nicht umbenennen, und niemand fuende den Grund.
     *
     * Ohne Land in der Anfrage faellt die Regel auf das Land der bearbeiteten Stadt
     * zurueck. Gibt es auch das nicht, greift sie gar nicht — eine Regel, die ihren
     * eigenen Bezug nicht kennt, soll nichts abweisen.
     */
    protected function uniqueCityName(?City $city = null): array
    {
        $city ??= $this->cityUnderEdit();

        if (filter_var($this->input('confirm_duplicate', false), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $countryId = $this->input('country_id', $city?->country_id);

        if ($countryId === null) {
            return [];
        }

        $rule = Rule::unique('cities', 'name')->where('country_id', $countryId);

        return [$city === null ? $rule : $rule->ignore($city->getKey())];
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
