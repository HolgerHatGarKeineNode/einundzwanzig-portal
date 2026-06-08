<?php

namespace App\Mcp\Tools\MeetupEvent;

use App\Http\Requests\Api\UpdateMeetupEventRequest;
use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert einen bestehenden Meetup-Termin. Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateMeetupEventTool extends Tool
{
    public function handle(Request $request): Response
    {
        $meetupEvent = MeetupEvent::find($request->get('id'));

        if (! $meetupEvent) {
            return Response::error('Meetup-Termin nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $meetupEvent)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Meetup-Termin ändern.');
        }

        $validated = $request->validate((new UpdateMeetupEventRequest)->rules());

        $meetupEvent->update($validated);

        return Response::json(MeetupEventResource::make($meetupEvent->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Meetup-Termins.')->required(),
            'meetup_id' => $schema->integer()->description('ID des zugehörigen Meetups.'),
            'start' => $schema->string()->description('Startzeitpunkt als Datum/Uhrzeit (z. B. 2026-08-01 18:00:00).'),
            'location' => $schema->string()->description('Veranstaltungsort.'),
            'description' => $schema->string()->description('Beschreibung des Termins.'),
            'link' => $schema->string()->description('Link zum Termin (URL).'),
            'recurrence_type' => $schema->string()->description('Wiederholungstyp.'),
            'recurrence_day_of_week' => $schema->string()->description('Wochentag der Wiederholung.'),
            'recurrence_day_position' => $schema->string()->description('Position des Wochentags im Monat.'),
            'recurrence_interval' => $schema->integer()->description('Wiederholungsintervall.'),
            'recurrence_end_date' => $schema->string()->description('Enddatum der Wiederholung.'),
        ];
    }
}
