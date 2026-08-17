<?php

namespace App\Mcp\Tools\CourseEvent;

use App\Http\Requests\Api\StoreCourseEventRequest;
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

#[Description('Legt ein neues Kurs-Event für den authentifizierten Referenten an. Kurs und Stadt werden über ihre Namen angegeben; die genaue Adresse steht als Freitext in "location". Der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateCourseEventTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! (bool) $user->is_lecturer) {
            return Response::error('Nur Referenten (is_lecturer) dürfen Kurs-Events anlegen.');
        }

        if (! $this->present($request->get('course_id'))) {
            $course = $this->resolveOwnedByName($request, Course::class, 'Kurse', 'course');

            if ($course instanceof Response) {
                return $course;
            }

            $request->merge(['course_id' => $course->id]);
        }

        // Optional (required: false): a course event may be announced before its place
        // is settled, so an omitted city must not abort the call.
        if ($error = $this->mergeForeignKey($request, 'city', 'city_id', City::query(), 'Städte', false)) {
            return $error;
        }

        $storeRequest = new StoreCourseEventRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $courseEvent = CourseEvent::create($validated);

        return Response::json(CourseEventResource::make($courseEvent->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'course' => $schema->string()->description('Name deines Kurses, zu dem das Event gehört. Wird automatisch aufgelöst – sonst zuerst search-courses aufrufen.'),
            'course_id' => $schema->integer()->description('Optional: ID des Kurses, falls bereits bekannt (Alternative zu "course").'),
            'city' => $schema->string()->description('Name der Stadt, in der das Event stattfindet. Wird automatisch aufgelöst – bei Bedarf per search-cities den genauen Namen ermitteln.'),
            'city_id' => $schema->integer()->description('Optional: ID der Stadt, falls bereits bekannt (Alternative zu "city").'),
            'location' => $schema->string()->description('Veranstaltungsort als Freitext, z. B. "Bitcoin-Café, Hauptstraße 1".'),
            'osm_type' => $schema->string()->description('Optional: OpenStreetMap-Objekttyp des Orts ("node", "way" oder "relation"). Nur zusammen mit osm_id sinnvoll.'),
            'osm_id' => $schema->integer()->description('Optional: OpenStreetMap-ID des Orts. Nur zusammen mit osm_type sinnvoll.'),
            'osm_name' => $schema->string()->description('Optional: Name des Orts laut OpenStreetMap.'),
            'osm_address' => $schema->string()->description('Optional: Adresse des Orts laut OpenStreetMap.'),
            'osm_lat' => $schema->number()->description('Optional: Breitengrad des Orts (-90 bis 90).'),
            'osm_lon' => $schema->number()->description('Optional: Längengrad des Orts (-180 bis 180).'),
            'from' => $schema->string()->description('Startzeitpunkt (Datum/Uhrzeit).')->required(),
            'to' => $schema->string()->description('Endzeitpunkt (Datum/Uhrzeit), gleich oder nach dem Start.')->required(),
            'link' => $schema->string()->description('URL mit weiteren Informationen zum Kurs-Event.')->required(),
        ];
    }
}
