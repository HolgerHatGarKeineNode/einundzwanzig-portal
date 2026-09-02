<?php

namespace App\Http\Controllers;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\Display;
use Spatie\IcalendarGenerator\Enums\EventStatus;

class DownloadMeetupCalendar extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        if ($request->has('meetup')) {
            $validated = $request->validate([
                'meetup' => ['required', 'integer'],
            ]);

            $meetup = Meetup::query()
                ->with([
                    'meetupEvents.meetup',
                ])
                ->findOrFail($validated['meetup']);
            $events = $meetup->meetupEvents()
                ->with(['meetup', 'tags'])
                ->where('start', '>=', now())
                ->get();
            $fallbackImageUrl = $meetup->getFirstMediaUrl('logo') ?: null;
            $fallbackImageMime = $meetup->getFirstMedia('logo')?->mime_type;
        } elseif ($request->has('my')) {
            $validated = $request->validate([
                'my' => ['required', 'array'],
                'my.*' => ['integer'],
            ]);

            $ids = $validated['my'];
            if (auth()->check()) {
                $ownedIds = auth()->user()->meetups->pluck('id')->all();
                $ids = array_values(array_intersect($ids, $ownedIds));
            }

            $events = MeetupEvent::query()
                ->with([
                    'meetup',
                    'tags',
                ])
                ->where('start', '>=', now())
                ->whereHas('meetup', fn ($query) => $query->whereIn('meetups.id', $ids))
                ->get();
            $fallbackImageUrl = asset('img/einundzwanzig-horizontal.png');
            $fallbackImageMime = 'image/png';
        } else {
            $events = MeetupEvent::query()
                ->with([
                    'meetup',
                    'tags',
                ])
                ->where('start', '>=', now())
                ->get();
            $fallbackImageUrl = asset('img/einundzwanzig-horizontal.png');
            $fallbackImageMime = 'image/png';
        }

        $timezone = new DateTimeZone(config('app.domain_timezone', 'UTC'));

        $entries = [];
        foreach ($events as $event) {
            $entries[] = $this->buildEntry($event, $timezone, $fallbackImageUrl, $fallbackImageMime);
        }

        $calendar = Calendar::create(config('app.name'))
            ->event($entries);

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8');
    }

    private function buildEntry(MeetupEvent $event, DateTimeZone $timezone, ?string $fallbackImageUrl, ?string $fallbackImageMime): Event
    {
        $entry = Event::create($event->title ?: $event->meetup->name)
            // Stabil ueber Umbenennungen von Meetup ODER Event hinweg — anders als
            // vorher, wo der Meetup-Name Teil der UID war und ein abonnierter Client
            // nach jeder Umbenennung ein Duplikat statt eines Updates saehe.
            ->uniqueIdentifier('meetup-event-'.$event->id.'@einundzwanzig.space')
            // Es gibt keine eigene Revisionsspalte; updated_at waechst bei jedem
            // Speichern monoton, was fuer SEQUENCE (RFC 5545) genau die geforderte
            // Eigenschaft ist — Clients vergleichen nur, ob der Wert gestiegen ist.
            ->sequence($event->updated_at?->getTimestamp() ?? 0)
            ->status(EventStatus::Confirmed)
            ->startsAt($event->start->copy()->setTimezone($timezone));

        if ($event->end) {
            $entry->endsAt($event->end->copy()->setTimezone($timezone));
        }

        $location = collect([$event->osm_name, $event->osm_address])->filter()->implode(', ');
        if ($location !== '') {
            $entry->address($location);
        }

        $description = $this->buildDescription($event);
        if ($description !== null) {
            $entry->description($description);
        }

        if ($event->link) {
            $entry->url($event->link);
        }

        $logo = $event->meetup->getFirstMedia('logo');
        $imageUrl = $logo?->getUrl() ?: $fallbackImageUrl;
        $imageMime = $logo?->mime_type ?? $fallbackImageMime;
        if ($imageUrl) {
            // BADGE ist die von Apple Calendar unterstuetzte Darstellung fuer ein
            // kleines Icon neben dem Termin; Clients ohne IMAGE-Unterstuetzung
            // ignorieren die Property schlicht (RFC 7986) — das ist der Fallback.
            $entry->image($imageUrl, $imageMime, Display::Badge);
        }

        return $entry;
    }

    private function buildDescription(MeetupEvent $event): ?string
    {
        $tagLine = $event->tags->isNotEmpty()
            ? '['.$event->tags->map(fn ($tag) => $tag->displayName())->implode(',').']'
            : null;

        $body = $event->description !== null && trim($event->description) !== ''
            ? $event->description
            : null;

        $lines = array_values(array_filter([$tagLine, $body], fn (?string $line) => $line !== null));

        return $lines === [] ? null : implode("\n\n", $lines);
    }
}
