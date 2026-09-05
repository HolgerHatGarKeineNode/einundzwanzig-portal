<?php

namespace App\Mcp\Tools\MeetupEvent;

use App\Actions\MeetupEvents\CreateMeetupEventSeries;
use App\Http\Requests\Api\StoreMeetupEventRequest;
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

#[Description('Legt einen neuen Meetup-Termin für eines der eigenen Meetups an. Das Meetup wird über seinen Namen angegeben; der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateMeetupEventTool extends Tool
{
    use ResolvesEntities;
    use ResolvesEventTags;

    public function __construct(private readonly CreateMeetupEventSeries $createSeries) {}

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', MeetupEvent::class)) {
            return Response::error('Nicht berechtigt, einen Meetup-Termin anzulegen.');
        }

        if (! $this->present($request->get('meetup_id'))) {
            $meetup = $this->resolveInScope(
                Meetup::query()->ledBy($user->getAuthIdentifier()),
                $request,
                'Meetups',
                'meetup',
            );

            if ($meetup instanceof Response) {
                return $meetup;
            }

            $request->merge(['meetup_id' => $meetup->id]);
        }

        // Nur Leader/Ersteller des Ziel-Meetups dürfen Termine anlegen (gleiche
        // Berechtigung wie die Stammdaten). Greift auch, wenn meetup_id direkt
        // übergeben wurde und damit den ledBy-Scope oben umgeht.
        $targetMeetup = Meetup::find($request->get('meetup_id'));

        if ($targetMeetup === null || Gate::forUser($user)->denies('update', $targetMeetup)) {
            return Response::error('Nur Leader oder der Ersteller dürfen Termine für dieses Meetup anlegen.');
        }

        $storeRequest = new StoreMeetupEventRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        /*
         * Tags are resolved BEFORE anything is written, and they are resolved in the
         * tool rather than in StoreMeetupEventRequest (issue #117).
         *
         * The request object is shared with the public REST API, so a `tags` rule there
         * would hand that API a new write capability as a side effect of an MCP ticket.
         * Keeping it out also means `tags` never appears in $validated — Validator only
         * returns keys it has a rule for — and that is what keeps the name list away
         * from MeetupEvent::create() below. It matters: HasTags::setTagsAttribute()
         * would queue the raw strings and the `created` hook would push them through
         * Tag::findOrCreate() with a null type, inventing a tag per unknown name. This
         * feature must never create a tag.
         */
        $tags = $this->resolveTagArgument($request);

        if ($tags instanceof Response) {
            return $tags;
        }

        /*
         * Serie oder Einzeltermin — dieselbe Weiche wie in
         * {@see \App\Http\Controllers\Api\MeetupEventController::store()}.
         *
         * Hier stand nur `MeetupEvent::create($validated)`. Mit `recurrence_type` UND
         * `recurrence_end_date` entstand damit EIN Termin, der Serienmetadaten trug, aber
         * kein einziges weiteres Vorkommen hatte. Das war nicht bloss unvollstaendig: der
         * `hasActiveRecurrence`-Zweig in {@see \App\Models\Meetup::recalculateActivity()}
         * fragt genau diese zwei Felder ab und haette das Meetup auf aktiv gestellt,
         * obwohl kein Termin in der Zukunft liegt — samt Meldung in den oeffentlichen
         * Aenderungs-Feed. Ueber die Action entstehen die Vorkommen wirklich, und der
         * Zweig ist wieder wahr, weil er wahr ist.
         */
        if (! empty($validated['recurrence_type']) && ! empty($validated['recurrence_end_date'])) {
            $events = $this->createSeries->handle($validated);

            // Every occurrence of a series carries the same tags — the same rule the
            // Livewire editor follows, where one selection is applied to the whole
            // series rather than to its first date only.
            $events->each(function (MeetupEvent $event) use ($tags): void {
                if ($tags !== null) {
                    $event->syncTagsWithType($tags->all(), self::EVENT_TAG_TYPE);
                }

                $event->load('tags');
            });

            return Response::json(MeetupEventResource::collection($events)->resolve());
        }

        $meetupEvent = MeetupEvent::create($validated);

        if ($tags !== null) {
            $meetupEvent->syncTagsWithType($tags->all(), self::EVENT_TAG_TYPE);
        }

        // load('tags') so the answer shows what was actually attached: the resource
        // emits tags under whenLoaded(), so without it the key would be absent and the
        // caller could not tell "no tags" from "this tool does not do tags".
        return Response::json(MeetupEventResource::make($meetupEvent->fresh()->load('tags'))->resolve());
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
            'title' => $schema->string()->description('Optionaler Titel des Termins. Ohne Titel erscheint der Name des Meetups.'),
            'end' => $schema->string()->description('Optionales Ende DIESES Termins als Datum/Uhrzeit. Nicht zu verwechseln mit recurrence_end_date, das die Serie beendet.'),
            'location' => $schema->string()->description('Veranstaltungsort.'),
            'description' => $schema->string()->description('Beschreibung des Termins.'),
            'link' => $schema->string()->description('VERALTET, bitte "links" verwenden. Wird als erster Eintrag der Link-Liste gespeichert. Werden beide angegeben, gewinnt "links".'),
            'links' => $schema->array()->items($schema->object([
                'url' => $schema->string()->description('Die URL selbst.')->required(),
                'label' => $schema->string()->description('Optionale Bezeichnung, z. B. "Meetup.com". Ohne Bezeichnung wird die blanke URL angezeigt.'),
            ]))->max(MeetupEvent::MAX_LINKS)->description('Alle Orte, an denen der Termin angekündigt ist — Meetup.com, Luma, eigene Website, Telegram, Nostr. Höchstens '.MeetupEvent::MAX_LINKS.' Einträge in der gewünschten Reihenfolge; weglassen oder [] bedeutet keine Links. Ein sechster Eintrag wird abgelehnt, nichts wird still verworfen. Bei einer Serie erhalten alle Vorkommen dieselbe Liste.'),
            'tags' => $schema->array()->items($schema->string())->description('Themen-Tags dieses Termins, als NAMEN (z. B. ["Vortrag", "Einsteiger"]). Zulässig sind ausschließlich die Namen aus list-event-tags; erkannt wird jede der neun Sprachen, Groß-/Kleinschreibung egal. Ein unbekannter oder mehrdeutiger Name wird abgelehnt und es wird NIE ein Tag neu angelegt — dann bricht der ganze Aufruf ab, es entsteht kein Termin. Bei einer Serie erhalten alle Vorkommen dieselben Tags.'),
            'recurrence_type' => $schema->string()->description('Wiederholungstyp.'),
            'recurrence_day_of_week' => $schema->string()->description('Wochentag der Wiederholung.'),
            'recurrence_day_position' => $schema->string()->description('Position des Wochentags im Monat.'),
            'recurrence_interval' => $schema->integer()->description('Wiederholungsintervall.'),
            'recurrence_end_date' => $schema->string()->description('Enddatum der Wiederholung.'),
        ];
    }
}
