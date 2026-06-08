<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\City\CreateCityTool;
use App\Mcp\Tools\City\ListMyCitiesTool;
use App\Mcp\Tools\City\ShowMyCityTool;
use App\Mcp\Tools\City\UpdateCityTool;
use App\Mcp\Tools\Course\CreateCourseTool;
use App\Mcp\Tools\Course\UpdateCourseTool;
use App\Mcp\Tools\CourseEvent\CreateCourseEventTool;
use App\Mcp\Tools\CourseEvent\ListMyCourseEventsTool;
use App\Mcp\Tools\CourseEvent\UpdateCourseEventTool;
use App\Mcp\Tools\Lecturer\CreateLecturerTool;
use App\Mcp\Tools\Lecturer\ListMyLecturersTool;
use App\Mcp\Tools\Lecturer\ShowMyLecturerTool;
use App\Mcp\Tools\Lecturer\UpdateLecturerTool;
use App\Mcp\Tools\Meetup\CreateMeetupTool;
use App\Mcp\Tools\Meetup\ListMyMeetupsTool;
use App\Mcp\Tools\Meetup\ShowMyMeetupTool;
use App\Mcp\Tools\Meetup\UpdateMeetupTool;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\ListMyMeetupEventsTool;
use App\Mcp\Tools\MeetupEvent\ShowMyMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\UpdateMeetupEventTool;
use App\Mcp\Tools\Search\ListCountriesTool;
use App\Mcp\Tools\Search\SearchCitiesTool;
use App\Mcp\Tools\Search\SearchCoursesTool;
use App\Mcp\Tools\Search\SearchLecturersTool;
use App\Mcp\Tools\Search\SearchVenuesTool;
use App\Mcp\Tools\Venue\CreateVenueTool;
use App\Mcp\Tools\Venue\ListMyVenuesTool;
use App\Mcp\Tools\Venue\ShowMyVenueTool;
use App\Mcp\Tools\Venue\UpdateVenueTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Einundzwanzig API')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Dieser Server spiegelt die authentifizierte Einundzwanzig-API. Jeder Aufruf läuft im Kontext
des per Sanctum-Token angemeldeten Nutzers; beim Anlegen wird der Ersteller (created_by)
automatisch auf diesen Nutzer gesetzt. Schreib- und Eigentums-Operationen (update, my-*) sind
nur für den Ersteller oder einen Super-Admin erlaubt.

Fremdschlüssel (city_id, venue_id, lecturer_id, course_id) zuerst über die search-* Tools
auflösen, bevor ein Datensatz angelegt oder aktualisiert wird.
TXT)]
class EinundzwanzigServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Meetups
        CreateMeetupTool::class,
        UpdateMeetupTool::class,
        ListMyMeetupsTool::class,
        ShowMyMeetupTool::class,

        // Meetup-Events
        CreateMeetupEventTool::class,
        UpdateMeetupEventTool::class,
        ListMyMeetupEventsTool::class,
        ShowMyMeetupEventTool::class,

        // Städte
        CreateCityTool::class,
        UpdateCityTool::class,
        ListMyCitiesTool::class,
        ShowMyCityTool::class,

        // Veranstaltungsorte
        CreateVenueTool::class,
        UpdateVenueTool::class,
        ListMyVenuesTool::class,
        ShowMyVenueTool::class,

        // Referenten
        CreateLecturerTool::class,
        UpdateLecturerTool::class,
        ListMyLecturersTool::class,
        ShowMyLecturerTool::class,

        // Kurse
        CreateCourseTool::class,
        UpdateCourseTool::class,

        // Kurs-Events
        ListMyCourseEventsTool::class,
        CreateCourseEventTool::class,
        UpdateCourseEventTool::class,

        // Suche / Stammdaten-Lookups
        SearchCitiesTool::class,
        SearchVenuesTool::class,
        SearchLecturersTool::class,
        SearchCoursesTool::class,
        ListCountriesTool::class,
    ];
}
