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
use App\Mcp\Tools\Meetup\AddMeetupToMineTool;
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
use App\Mcp\Tools\Search\SearchMeetupsTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminCreateRecordTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminDescribeModelTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminListModelsTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminListRecordsTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminShowRecordTool;
use App\Mcp\Tools\SuperAdmin\SuperAdminUpdateRecordTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('EINUNDZWANZIG API')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Dieser Server spiegelt die authentifizierte EINUNDZWANZIG-API. Jeder Aufruf läuft im Kontext
des angemeldeten Nutzers; beim Anlegen wird der Ersteller (created_by) automatisch gesetzt.
Schreib- und Eigentums-Operationen (update, show-my-*) sind nur für den Ersteller oder einen
Super-Admin erlaubt.

WICHTIG – niemals nach numerischen IDs fragen: Nutzer kennen keine internen IDs. Referenziere
Entitäten immer über ihren NAMEN:
- Eigene Datensätze ändern/anzeigen: zuerst das passende list-my-* Tool aufrufen
  (list-my-meetups, list-my-cities, list-my-lecturers, list-my-course-events),
  dem Nutzer die Namen als Auswahlliste präsentieren und ihn wählen lassen. Dann das update-/
  show-my-* Tool mit dem gewählten Namen aufrufen (Parameter z. B. "meetup", "city",
  "lecturer", "course").
- Fremdschlüssel beim Anlegen (Stadt, Land, Referent, Kurs): den Namen
  übergeben (Parameter z. B. "city", "country", "lecturer", "course"); bei Unsicherheit
  vorher mit search-cities / search-lecturers / search-courses / list-countries
  den genauen Namen ermitteln.
- Veranstaltungsorte sind KEINE eigene Entität: Termine tragen ihre Stadt ("city") und den
  Ort als Freitext ("location"); der Kartenpunkt steht, sofern bekannt, in den osm_*-Feldern.
Bevor ein NEUES Meetup angelegt wird (create-meetup): IMMER zuerst mit search-meetups nach
einem bestehenden Meetup suchen – sowohl nach dem Namen als auch nach dem Stadtnamen. Existiert
bereits ein passendes Meetup, KEIN Duplikat anlegen, sondern dem Nutzer das gefundene Meetup
nennen. Nur wenn die Suche kein passendes Meetup liefert, den Nutzer fragen, ob ein neues
Meetup erstellt werden soll – und erst nach Bestätigung create-meetup aufrufen.

Termine/Events (Meetup-Termine, Kurs-Events) haben keinen Namen. Hier zuerst list-my-meetup-
events bzw. list-my-course-events aufrufen, dem Nutzer die Einträge zur Auswahl anbieten und
die ID des gewählten Eintrags übergeben – ebenfalls ohne den Nutzer nach der ID zu fragen.

Die Tools lösen Namen serverseitig auf. Bei Mehrdeutigkeit oder fehlendem Treffer liefern sie
eine Liste der passenden Einträge zurück – diese dem Nutzer zur Auswahl anbieten. Die *_id-
Parameter sind nur ein optionaler Fallback, falls die ID bereits bekannt ist.

Super-Admins sehen zusätzlich generische super-admin-* Tools, mit denen JEDES Model bearbeitet
werden kann (ohne Ownership-Beschränkung). Vorgehen: erst super-admin-list-models, dann
super-admin-describe-model (für die Felder), dann super-admin-list-records / -show-record zum
Finden und schließlich super-admin-create-record / -update-record zum Bearbeiten.
Sicherheitskritische Felder (Passwörter, Auth-Tokens, Rollen) lassen sich über diese Tools
NICHT setzen – Rollen und Passwörter werden ausschließlich über die dafür vorgesehenen Wege
verwaltet.
TXT)]
class EinundzwanzigServer extends Server
{
    /**
     * tools/list wird vom Paket cursor-paginiert (Default 15/Seite). Manche Clients
     * (z. B. der Claude.ai-Web-Connector) laden nur die erste Seite und folgen dem
     * nextCursor nicht – dann fehlt die Hälfte der Tools. Wir heben die Seitengröße an,
     * sodass alle Tools auf eine Seite passen.
     */
    public int $maxPaginationLength = 1000;

    public int $defaultPaginationLength = 1000;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Meetups
        CreateMeetupTool::class,
        UpdateMeetupTool::class,
        AddMeetupToMineTool::class,
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
        SearchMeetupsTool::class,
        SearchCitiesTool::class,
        SearchLecturersTool::class,
        SearchCoursesTool::class,
        ListCountriesTool::class,

        // Super-Admin: generische Tools für ALLE Models (nur für Super-Admins sichtbar,
        // via shouldRegister; created_by/Ownership-Beschränkungen entfallen hier bewusst).
        SuperAdminListModelsTool::class,
        SuperAdminDescribeModelTool::class,
        SuperAdminListRecordsTool::class,
        SuperAdminShowRecordTool::class,
        SuperAdminCreateRecordTool::class,
        SuperAdminUpdateRecordTool::class,
    ];
}
