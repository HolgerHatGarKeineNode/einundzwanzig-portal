<?php

namespace App\Mcp\Tools\MeetupEvent;

use App\Http\Requests\Api\StoreMeetupEventRequest;
use App\Http\Resources\MeetupEventResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt einen neuen Meetup-Termin für eines der eigenen Meetups an. Das Meetup wird über seinen Namen angegeben; der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateMeetupEventTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', MeetupEvent::class)) {
            return Response::error('Nicht berechtigt, einen Meetup-Termin anzulegen.');
        }

        if (! $this->present($request->get('meetup_id'))) {
            $meetup = $this->resolveInScope(
                Meetup::query()->associatedWith($user->getAuthIdentifier()),
                $request,
                'Meetups',
                'meetup',
            );

            if ($meetup instanceof Response) {
                return $meetup;
            }

            $request->merge(['meetup_id' => $meetup->id]);
        }

        $storeRequest = new StoreMeetupEventRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $meetupEvent = MeetupEvent::create($validated);

        return Response::json(MeetupEventResource::make($meetupEvent->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'meetup' => $schema->string()->description('Name deines Meetups, zu dem der Termin gehört (z. B. "Einundzwanzig Ansbach"). Wird automatisch aufgelöst – sonst zuerst list-my-meetups aufrufen und den Nutzer auswählen lassen.'),
            'meetup_id' => $schema->integer()->description('Optional: ID des Meetups, falls bereits bekannt (Alternative zu "meetup").'),
            'start' => $schema->string()->description('Startzeitpunkt als Datum/Uhrzeit (z. B. 2026-08-01 18:00:00).')->required(),
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
