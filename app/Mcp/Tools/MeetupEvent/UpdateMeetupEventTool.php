<?php

namespace App\Mcp\Tools\MeetupEvent;

use App\Http\Requests\Api\UpdateMeetupEventRequest;
use App\Http\Resources\MeetupEventResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Mcp\Tools\Concerns\ResolvesEventTags;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert einen bestehenden Meetup-Termin. Nur der Ersteller des Termins, ein Leader des zugehörigen Meetups oder ein Super-Admin darf ihn ändern.')]
class UpdateMeetupEventTool extends Tool
{
    use ResolvesEntities;
    use ResolvesEventTags;

    public function handle(Request $request): Response
    {
        $meetupEvent = MeetupEvent::find($request->get('id'));

        if (! $meetupEvent) {
            return Response::error('Meetup-Termin nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $meetupEvent)) {
            return Response::error('Nur der Ersteller des Termins, ein Leader des Meetups oder ein Super-Admin darf diesen Meetup-Termin ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'meetup', 'meetup_id', Meetup::query()->ledBy($user->getAuthIdentifier()), 'Meetups', false)) {
            return $error;
        }

        $validated = $request->validate((new UpdateMeetupEventRequest)->rules());

        /*
         * Resolved BEFORE the update, so a name that cannot be resolved leaves the
         * event exactly as it was — a half-applied tag list is worse than a rejected
         * one, and so is an event whose other fields moved while its tags did not.
         *
         * Resolution lives here and not in UpdateMeetupEventRequest because that
         * request is shared with the public REST API: a `tags` rule there would give
         * that API a new write capability as a side effect of an MCP ticket. The REST
         * API therefore still cannot write tags.
         *
         * null means "leave the tags alone" — both when the key is missing and when it
         * is explicitly null. See {@see ResolvesEventTags::resolveTagArgument()} for
         * why the null case is spelled out rather than left to `sometimes` (issue #70).
         */
        $tags = $this->resolveTagArgument($request);

        if ($tags instanceof Response) {
            return $tags;
        }

        $meetupEvent->update($validated);

        if ($tags !== null) {
            // Replaces the whole set in one step, scoped to the event tag group so
            // tags of any other type on this event stay untouched. An empty collection
            // is therefore "remove every tag", not "do nothing".
            $meetupEvent->syncTagsWithType($tags->all(), self::EVENT_TAG_TYPE);
        }

        return Response::json(MeetupEventResource::make($meetupEvent->fresh()->load('tags'))->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Meetup-Termins (über list-my-meetup-events ermitteln; nicht den Nutzer danach fragen).')->required(),
            'meetup' => $schema->string()->description('Name des zugehörigen Meetups, falls geändert werden soll (wird automatisch aufgelöst).'),
            'meetup_id' => $schema->integer()->description('Optional: ID des Meetups (Alternative zu "meetup").'),
            'start' => $schema->string()->description('Startzeitpunkt als Datum/Uhrzeit (z. B. 2026-08-01 18:00:00).'),
            'title' => $schema->string()->description('Optionaler Titel des Termins.'),
            'end' => $schema->string()->description('Optionales Ende DIESES Termins. Nicht recurrence_end_date, das die Serie beendet.'),
            'location' => $schema->string()->description('Veranstaltungsort.'),
            'description' => $schema->string()->description('Beschreibung des Termins.'),
            'link' => $schema->string()->description('Link zum Termin (URL).'),
            'tags' => $schema->array()->items($schema->string())->description('Ersetzt die Themen-Tags des Termins VOLLSTÄNDIG, als NAMEN (z. B. ["Vortrag", "Einsteiger"]). Weglassen lässt die bestehenden Tags unverändert; [] entfernt alle. Zulässig sind ausschließlich die Namen aus list-event-tags; erkannt wird jede der neun Sprachen, Groß-/Kleinschreibung egal. Ein unbekannter oder mehrdeutiger Name wird abgelehnt, der Termin bleibt dabei unverändert, und es wird NIE ein Tag neu angelegt.'),
            'recurrence_type' => $schema->string()->description('Wiederholungstyp.'),
            'recurrence_day_of_week' => $schema->string()->description('Wochentag der Wiederholung.'),
            'recurrence_day_position' => $schema->string()->description('Position des Wochentags im Monat.'),
            'recurrence_interval' => $schema->integer()->description('Wiederholungsintervall.'),
            'recurrence_end_date' => $schema->string()->description('Enddatum der Wiederholung.'),
        ];
    }
}
