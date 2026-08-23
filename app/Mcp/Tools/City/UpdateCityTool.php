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

#[Description('Aktualisiert eine deiner Städte (per Name angegeben). Nur der Ersteller oder ein Super-Admin darf sie ändern. Neben Name, Land und Koordinaten lassen sich Region, Einwohnerzahl samt Stichjahr sowie die OpenStreetMap-Referenz (osm_type/osm_id und die abgeleiteten Felder) und die Wikidata-/Wikipedia-Verweise pflegen.')]
class UpdateCityTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $city = $this->resolveOwnedByName($request, City::class, 'Städte', 'city');

        if ($city instanceof Response) {
            return $city;
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $city)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diese Stadt ändern.');
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

        $city->update($validated);

        return Response::json(CityResource::make($city->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()->description('Name der zu ändernden Stadt (aus deinen Städten, siehe list-my-cities).'),
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
