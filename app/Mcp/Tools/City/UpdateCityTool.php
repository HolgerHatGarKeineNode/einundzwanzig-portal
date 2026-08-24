<?php

namespace App\Mcp\Tools\City;

use App\Http\Requests\Api\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert eine BELIEBIGE Stadt (per Name angegeben) — nicht nur die eigenen. Anreichern darf jeder angemeldete Nutzer: OpenStreetMap-Referenz (osm_type/osm_id und die abgeleiteten Felder), Wikidata, Wikipedia und Koordinaten. Die Identitätsfelder Name, Land, Region, Einwohnerzahl und Stichjahr darf nur der Ersteller, ein City-Steward oder ein Super-Admin ändern; ein Versuch ohne diese Berechtigung wird abgelehnt, statt still zu verpuffen. Den genauen Namen vorher mit search-cities ermitteln.')]
class UpdateCityTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        /*
         * GLOBAL aufgeloest, nicht auf die eigenen Staedte eingeschraenkt (Issue #30).
         * `resolveOwnedByName()` filtert hart auf `created_by` und haette hier eine
         * zweite Grenze gezogen, die die Policy gar nicht mehr zieht: eine fremde
         * Stadt waere mit „nicht gefunden" beantwortet worden, obwohl der Nutzer sie
         * anreichern darf. Eine Berechtigungsgrenze, die als Suchergebnis auftritt,
         * ist nicht nachvollziehbar — und sie war der eigentliche Grund, warum die
         * Kante ueberhaupt uebersehen wurde.
         */
        $city = $this->present($request->get('id'))
            ? City::find($request->get('id'))
            : $this->resolveGlobalByName(City::query(), $request->get('city'), 'Städte');

        if ($city instanceof Response) {
            return $city;
        }

        if ($city === null) {
            return Response::error('Stadt nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $city)) {
            return Response::error('Nur angemeldete Nutzer dürfen Städte bearbeiten.');
        }

        if ($error = $this->mergeForeignKey($request, 'country', 'country_id', Country::query(), 'Land', false)) {
            return $error;
        }

        /*
         * Die Region wird gegen das MASSGEBLICHE Land aufgeloest, nicht global: ein
         * Bundesstaat "Georgia" existiert in den USA und als Land daneben, und
         * Regionsnamen wiederholen sich zwischen Laendern. Massgeblich ist ein in
         * diesem Aufruf mitgeschicktes `country_id` — sonst das der bestehenden Stadt.
         * Dieselbe Reihenfolge benutzt UpdateCityRequest fuer seine exists-Regel.
         */
        $countryId = $request->get('country_id') ?? $city->country_id;

        if ($error = $this->mergeForeignKey($request, 'region', 'region_id', Region::query()->where('country_id', $countryId), 'Region', false)) {
            return $error;
        }

        /*
         * `forCity()` statt `new UpdateCityRequest`: die unique-Regel auf `name` muss die
         * Stadt sich selbst nachsehen, und die Regionsregel braucht das mitgeschickte
         * Land. Beides kennt ein Request nur, wenn er die Eingaben und die Stadt hat —
         * durch den Router laeuft dieser Aufruf nicht.
         */
        $validated = $request->validate(
            UpdateCityRequest::forCity($city, $request->all())->rules()
        );

        /*
         * Die zweite Ability, und zwar NACH der Validierung: `identityChanges()`
         * vergleicht gegen den Bestand, und erst die validierten Werte sind die, die
         * geschrieben wuerden. Gegen die Rohdaten geprueft, koennte ein ungueltiges
         * Feld einen 403 ausloesen, wo ein Validierungsfehler die ehrlichere Antwort
         * ist.
         */
        $identityChanges = $city->identityChanges($validated);

        if ($identityChanges !== [] && Gate::forUser($user)->denies('updateIdentity', $city)) {
            return Response::error(sprintf(
                'Diese Felder darf nur der Ersteller, ein City-Steward oder ein Super-Admin ändern: %s. Die OSM-Referenz, Wikidata, Wikipedia und die Koordinaten kannst du dagegen frei pflegen.',
                implode(', ', $identityChanges),
            ));
        }

        $city->update($validated);

        return Response::json(CityResource::make($city->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()->description('Name der zu ändernden Stadt — jede Stadt, nicht nur die eigenen. Vorher mit search-cities den genauen Namen ermitteln.'),
            'id' => $schema->integer()->description('Optional: ID der Stadt, falls bereits bekannt (Alternative zu "city").'),
            'country' => $schema->string()->description('Name des zugehörigen Landes (wird automatisch aufgelöst).'),
            'country_id' => $schema->integer()->description('Optional: ID des Landes (Alternative zu "country").'),
            'region' => $schema->string()->description('Name der Verwaltungsebene 1 — US-Bundesstaat, Bundesland, Provinz. Wird innerhalb des Landes aufgelöst.'),
            'region_id' => $schema->integer()->description('Optional: ID der Region (Alternative zu "region"). Muss zum Land der Stadt gehören.'),
            'name' => $schema->string()->description('Neuer Name der Stadt. Städtenamen sind global eindeutig.'),
            'longitude' => $schema->number()->description('Längengrad der Stadt.'),
            'latitude' => $schema->number()->description('Breitengrad der Stadt.'),
            'population' => $schema->integer()->description('Einwohnerzahl der Stadt.'),
            'population_date' => $schema->string()->description('Stichjahr oder Stichtag der Einwohnerzahl, z. B. "2024" oder "2011-05-09".'),
            'osm_type' => $schema->string()->description('Art des OpenStreetMap-Objekts: node, way oder relation. Nur zusammen mit osm_id gültig.'),
            'osm_id' => $schema->integer()->description('ID des OpenStreetMap-Objekts. Nur zusammen mit osm_type eindeutig.'),
            'osm_name' => $schema->string()->description('Name des Orts, wie OpenStreetMap ihn führt.'),
            'osm_address' => $schema->string()->description('Vollständige Adresszeile aus OpenStreetMap.'),
            'osm_lat' => $schema->number()->description('Breitengrad der OSM-Referenz (-90 bis 90).'),
            'osm_lon' => $schema->number()->description('Längengrad der OSM-Referenz (-180 bis 180).'),
            'wikidata' => $schema->string()->description('Wikidata-Q-ID des Orts, z. B. Q64.'),
            'wikipedia' => $schema->string()->description('Wikipedia-Verweis in OSM-Schreibweise: Sprachpräfix, Doppelpunkt, Titel — z. B. de:Berlin.'),
        ];
    }
}
