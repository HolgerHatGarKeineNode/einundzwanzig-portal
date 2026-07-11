<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Schlanke, schnelle Meetup-Liste für die mobile App.
 *
 * Bewusst getrennt von {@see MeetupMapController} (GET /api/meetups), damit die
 * Website-Karte und andere Konsumenten unverändert bleiben. Nur die Felder, die
 * die App-Liste und die App-Karte rendern (Name, Slug, Ort, Land, Geo, Logo,
 * nächster Termin) — kein Intro, keine Socials, keine RSVP-Zähler.
 *
 * Der Geschwindigkeitsgewinn kommt aus der Query: der nextEvent-Accessor des
 * Models feuert pro Meetup mehrere Abfragen (nächster Termin + zwei Zähler),
 * hier ersetzt durch EINE korrelierte Subquery auf den Start des nächsten
 * Termins. City/Country/Media werden eager geladen — konstante Query-Zahl
 * unabhängig von der Meetup-Anzahl (kein N+1).
 */
#[Group(name: 'Meetups', weight: 3)]
class MobileMeetupListController extends Controller
{
    /**
     * Meetup-Liste für die mobile App
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(): Collection
    {
        return Meetup::query()
            ->where('visible_on_map', true)
            ->select(['id', 'name', 'slug', 'city_id'])
            ->addSelect(['next_event_start' => MeetupEvent::query()
                ->select('start')
                ->whereColumn('meetup_id', 'meetups.id')
                ->where('start', '>=', now())
                ->orderBy('start')
                ->limit(1),
            ])
            ->with([
                'city:id,name,country_id,longitude,latitude',
                'city.country:id,code',
                'media',
            ])
            // Wie in der App: Meetups mit dem nächsten Termin zuerst, Rest ans
            // Ende, dann nach Name. Die App sortiert zwar erneut, aber so ist die
            // Antwort bereits korrekt geordnet (u. a. für ein späteres Limit).
            ->orderByRaw('next_event_start is null, next_event_start')
            ->orderBy('name')
            ->get()
            ->map(fn (Meetup $meetup): array => [
                'name' => $meetup->name,
                'slug' => $meetup->slug,
                'city' => $meetup->city?->name ?? '',
                'country' => str($meetup->city?->country?->code)->upper()->value(),
                'latitude' => (float) ($meetup->city?->latitude ?? 0),
                'longitude' => (float) ($meetup->city?->longitude ?? 0),
                // getFirstMedia (nicht getFirstMediaUrl): die 'logo'-Collection hat
                // eine Fallback-URL (Länder-Platzhalter). Ohne echtes Logo soll die
                // App den Initialen-Avatar zeigen, also null statt Platzhalter-URL.
                'logo' => $meetup->getFirstMedia('logo')?->getUrl(),
                // Gleiches Format wie GET /api/meetup-events (siehe MeetupEventController).
                'next_event_start' => $meetup->next_event_start
                    ? Carbon::parse($meetup->next_event_start)->format('Y-m-d H:i')
                    : null,
            ]);
    }
}
