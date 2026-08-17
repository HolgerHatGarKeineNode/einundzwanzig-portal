<?php

namespace App\Mcp\Tools\CourseEvent;

use App\Http\Requests\Api\UpdateCourseEventRequest;
use App\Http\Resources\CourseEventResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert ein bestehendes Kurs-Event. Nur der Ersteller oder ein Super-Admin darf es ändern.')]
class UpdateCourseEventTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $courseEvent = CourseEvent::find($request->get('id'));

        if (! $courseEvent) {
            return Response::error('Kurs-Event nicht gefunden.');
        }

        $user = $request->user();

        if (! $user instanceof User || ((int) $courseEvent->created_by !== $user->getAuthIdentifier() && ! $user->hasRole('super-admin'))) {
            return Response::error('Nur der Ersteller des Kurs-Events oder ein Super-Admin darf es ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'course', 'course_id', Course::query()->where('created_by', $user->getAuthIdentifier()), 'Kurse', false)) {
            return $error;
        }

        if ($error = $this->mergeForeignKey($request, 'city', 'city_id', City::query(), 'Städte', false)) {
            return $error;
        }

        $updateRequest = new UpdateCourseEventRequest;

        $validated = $request->validate(
            $updateRequest->rules(),
            $updateRequest->messages(),
        );

        $courseEvent->update($validated);

        return Response::json(CourseEventResource::make($courseEvent->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Kurs-Events (über list-my-course-events ermitteln; nicht den Nutzer danach fragen).')->required(),
            'course' => $schema->string()->description('Name des zugehörigen Kurses, falls geändert werden soll (wird automatisch aufgelöst).'),
            'course_id' => $schema->integer()->description('Optional: ID des Kurses (Alternative zu "course").'),
            'city' => $schema->string()->description('Name der Stadt, falls geändert werden soll (wird automatisch aufgelöst – bei Bedarf per search-cities den genauen Namen ermitteln).'),
            'city_id' => $schema->integer()->description('Optional: ID der Stadt (Alternative zu "city").'),
            'location' => $schema->string()->description('Veranstaltungsort als Freitext, z. B. "Bitcoin-Café, Hauptstraße 1".'),
            'osm_type' => $schema->string()->description('Optional: OpenStreetMap-Objekttyp des Orts ("node", "way" oder "relation"). Nur zusammen mit osm_id sinnvoll.'),
            'osm_id' => $schema->integer()->description('Optional: OpenStreetMap-ID des Orts. Nur zusammen mit osm_type sinnvoll.'),
            'osm_name' => $schema->string()->description('Optional: Name des Orts laut OpenStreetMap.'),
            'osm_address' => $schema->string()->description('Optional: Adresse des Orts laut OpenStreetMap.'),
            'osm_lat' => $schema->number()->description('Optional: Breitengrad des Orts (-90 bis 90).'),
            'osm_lon' => $schema->number()->description('Optional: Längengrad des Orts (-180 bis 180).'),
            'from' => $schema->string()->description('Startzeitpunkt (Datum/Uhrzeit).'),
            'to' => $schema->string()->description('Endzeitpunkt (Datum/Uhrzeit), gleich oder nach dem Start.'),
            'link' => $schema->string()->description('URL mit weiteren Informationen zum Kurs-Event.'),
        ];
    }
}
