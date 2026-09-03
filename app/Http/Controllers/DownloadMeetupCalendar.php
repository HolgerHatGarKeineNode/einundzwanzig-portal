<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
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
        // country/language/timezone werden bewusst NICHT ueber $request->validate()
        // gelesen: eine ValidationException waere ein 422 fuer ein Abo, das kein
        // Mensch mehr korrigieren kann (Kalender-Client, keine Session). Ein
        // unbekannter oder verstuemmelter Wert soll auf den Domain-Default fallen,
        // nicht die Zustellung abbrechen.
        $countryCode = $this->resolveCountryCode($request);
        $language = $this->resolveLanguage($request);

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
                ->when($countryCode, fn (Builder $query) => $this->scopeToCountry($query, $countryCode))
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
                ->when($countryCode, fn (Builder $query) => $this->scopeToCountry($query, $countryCode))
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
                ->when($countryCode, fn (Builder $query) => $this->scopeToCountry($query, $countryCode))
                ->get();
            $fallbackImageUrl = asset('img/einundzwanzig-horizontal.png');
            $fallbackImageMime = 'image/png';
        }

        $timezone = new DateTimeZone($this->resolveTimezone($request));

        $entries = [];
        foreach ($events as $event) {
            $entries[] = $this->buildEntry($event, $timezone, $fallbackImageUrl, $fallbackImageMime, $language);
        }

        $calendarName = $language !== null
            ? (config("lang-country.languages.{$language}.calendar_name") ?? config('app.name'))
            : config('app.name');

        $calendar = Calendar::create($calendarName)
            ->event($entries);

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8');
    }

    private function scopeToCountry(Builder $query, string $countryCode): Builder
    {
        return $query->whereHas('meetup.city.country', fn ($query) => $query->where('countries.code', $countryCode));
    }

    /**
     * `country` only ever scopes the feed CONTENT (the "all events" vs. "this
     * country only" button pair) — never the calendar name or timezone of the
     * output, unlike `language`/`timezone` below. That is why an unknown or
     * malformed value resolves to null (no filter) here instead of falling
     * back to the domain's own country: unlike language/timezone, where a
     * fallback only changes display, defaulting country to the domain would
     * silently narrow an existing subscription's content on a typo'd or
     * stale URL — the exact regression the "no `country` param" case is
     * required to avoid. No filter is the behavior every URL had before this
     * feature existed, so it is also the safe default for a value we can't
     * make sense of.
     */
    private function resolveCountryCode(Request $request): ?string
    {
        $requested = mb_strtolower(trim((string) $request->query('country', '')));

        if ($requested === '') {
            return null;
        }

        return Country::query()->where('code', $requested)->exists() ? $requested : null;
    }

    /**
     * Reihenfolge: URL-Parameter (muss ein in `lang-country.languages` gepflegter
     * Sprachcode sein), sonst null — der Aufrufer faellt dann exakt auf das
     * heutige Verhalten zurueck (Domain-`app.name`, Tag-Namen in der App-Locale).
     */
    private function resolveLanguage(Request $request): ?string
    {
        $requested = mb_strtolower(trim((string) $request->query('language', '')));

        return ($requested !== '' && is_array(config("lang-country.languages.{$requested}")))
            ? $requested
            : null;
    }

    /**
     * Reihenfolge: URL-Parameter (muss eine echte IANA-Kennung sein), sonst die
     * Domain-Zeitzone, sonst UTC — unveraendert gegenueber dem bisherigen Verhalten.
     */
    private function resolveTimezone(Request $request): string
    {
        $requested = trim((string) $request->query('timezone', ''));

        if ($requested !== '' && in_array($requested, DateTimeZone::listIdentifiers(), true)) {
            return $requested;
        }

        return config('app.domain_timezone', 'UTC');
    }

    private function buildEntry(MeetupEvent $event, DateTimeZone $timezone, ?string $fallbackImageUrl, ?string $fallbackImageMime, ?string $language): Event
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

        $location = $this->resolveLocation($event);
        if ($location !== null) {
            $entry->address($location);
        }

        $description = $this->buildDescription($event, $language);
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

    /**
     * Venue for LOCATION, in the order the repo owner settled on: the OSM pair
     * when the event has been matched to a map point, otherwise the free-text
     * `location` column, otherwise no property at all.
     *
     * The fallback is not cosmetic. Most rows carry free text only and no OSM
     * data (issue #36 sample: `location = "Schwabach"` with all six `osm_*`
     * null), so an OSM-only rule ships those subscriptions without any venue —
     * that was the regression 8e4f1be5 introduced when it replaced
     * `->address($event->location ?? __('no location set'))`. Dropping the
     * placeholder was right; dropping the column with it was not.
     *
     * OSM data does not get the free text appended: `osm_address` is the
     * formatted address of the same venue, so the two would repeat the place
     * (`"Café Central, Marktplatz 1, 99084 Erfurt, Schwabach"`).
     */
    private function resolveLocation(MeetupEvent $event): ?string
    {
        $osmLocation = collect([$event->osm_name, $event->osm_address])->filter()->implode(', ');

        if ($osmLocation !== '') {
            return $osmLocation;
        }

        $freeText = trim((string) $event->location);

        return $freeText !== '' ? $freeText : null;
    }

    private function buildDescription(MeetupEvent $event, ?string $language): ?string
    {
        // One bracket pair per tag, space-separated (issue #41) — "[A] [B]", not
        // "[A,B]". Beyond readability this keeps the separator out of RFC 5545's
        // escape set: a comma reaches the wire as "\," (see TextProperty), a
        // space reaches it verbatim.
        $tagLine = $event->tags->isNotEmpty()
            ? $event->tags->map(fn ($tag) => '['.$tag->displayName($language).']')->implode(' ')
            : null;

        $body = $event->description !== null && trim($event->description) !== ''
            ? $event->description
            : null;

        $lines = array_values(array_filter([$tagLine, $body], fn (?string $line) => $line !== null));

        return $lines === [] ? null : implode("\n\n", $lines);
    }
}
