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
     * Longest requested country code X-WR-CALNAME will repeat back. Two octets
     * for ISO 3166-1 alpha-2, three for alpha-3, five for a locale-shaped
     * "de-DE" — eight leaves headroom for all of them while keeping an
     * arbitrarily long query string out of the property.
     */
    private const UNKNOWN_COUNTRY_LABEL_LIMIT = 8;

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

        $calendar = Calendar::create($this->resolveCalendarName($language, $countryCode))
            ->event($entries);

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8');
    }

    /**
     * The case-insensitive comparison in `matchingCode()` stays load-bearing
     * after #77; only the direction in which it fails changed. While it was
     * case-sensitive, `?country=de` against an uppercase stored `DE` missed,
     * `resolveCountryCode()` returned null and NO filter was applied at all —
     * the whole world instead of one country (measured then: 5 events instead
     * of 2, fixed in #78). Today the same miss would deliver an empty feed
     * instead. Still wrong, but reported instead of silent.
     */
    private function scopeToCountry(Builder $query, string $countryCode): Builder
    {
        return $query->whereHas('meetup.city.country', fn ($query) => $query->matchingCode($countryCode));
    }

    /**
     * `country` only ever scopes the feed CONTENT (the "all events" vs. "this
     * country only" button pair) — never the timezone of the output, unlike
     * `language`/`timezone` below; it reaches the calendar NAME only to report
     * itself back (see `resolveCalendarName()`).
     *
     * A value that matches no country has three possible answers, and two of
     * them fail open. Both were considered; this is what #77 settled:
     *
     * - Fall back to the domain's own country — rejected. Unlike language and
     *   timezone, where a fallback only changes display, defaulting country to
     *   the domain would silently NARROW an existing subscription's content on
     *   a typo'd or stale URL, answering it with some other country's
     *   calendar. That is the regression the "no `country` param" case is
     *   required to avoid.
     * - Ignore the unusable parameter and return every country — this was the
     *   behavior until #77, and it is rejected too. A `country=` that is
     *   present states the intent to narrow, so returning the whole world
     *   delivers MORE than was asked for. It is the same shape of fail-open
     *   the casing bug had (#78): the gate misses, the filter is never set. A
     *   calendar subscription is set up once and then never looked at again,
     *   which is exactly why over-delivery goes unnoticed while an empty feed
     *   is reported within a day. Of the two failure directions, the reported
     *   one is the one to take.
     * - Pass the requested code through and let it match nothing — chosen. The
     *   subscriber gets what an unmatched filter means, no events, and
     *   X-WR-CALNAME names the code that matched nothing, so the empty feed
     *   reads as "this portal does not know 'zz'" rather than "this portal is
     *   broken". That is the answer to the objection in favor of ignoring it:
     *   hand-typed and pasted feed URLs are exactly why the code has to be
     *   visible in the client, not why the parameter should be dropped.
     *
     * Recognition itself therefore no longer happens here — an unrecognized
     * code is not filtered out, it is a filter that matches no row.
     */
    private function resolveCountryCode(Request $request): ?string
    {
        $requested = mb_strtolower(trim((string) $request->query('country', '')));

        return $requested !== '' ? $requested : null;
    }

    /**
     * X-WR-CALNAME is the only line a subscriber's client still shows once the
     * feed is empty, so an unrecognized `country=` is named there:
     * "EINUNDZWANZIG Portal (unknown country: zz)". Without it, "no events
     * matched your country" and "the portal is broken" are the same file. A
     * recognized code is NOT repeated back — an empty feed for a code this
     * portal knows really does mean "nothing coming up there", and renaming
     * every working subscription is not this issue's business.
     *
     * The requested value is user input reaching an output property, so it is
     * sanitized before it gets there rather than relying on the generator's
     * escaping (TextProperty escapes `\`, `"`, `,`, `;` and newlines — a
     * second line of defense, not the first): everything outside [a-z0-9-] is
     * dropped, which also removes any CR/LF that could split or extend the
     * property line, and the remainder is capped at
     * self::UNKNOWN_COUNTRY_LABEL_LIMIT so an arbitrarily long query string
     * cannot inflate the header field. The pattern runs WITHOUT `/u` on
     * purpose: on invalid UTF-8 a unicode pattern would return null instead of
     * stripping, and since only ASCII survives, no partial multibyte sequence
     * can. If nothing survives, the marker is emitted without a code.
     */
    private function resolveCalendarName(?string $language, ?string $countryCode): string
    {
        $name = $language !== null
            ? (config("lang-country.languages.{$language}.calendar_name") ?? config('app.name'))
            : config('app.name');

        if ($countryCode === null || Country::query()->matchingCode($countryCode)->exists()) {
            return $name;
        }

        $label = mb_substr(
            (string) preg_replace('/[^a-z0-9-]/', '', $countryCode),
            0,
            self::UNKNOWN_COUNTRY_LABEL_LIMIT
        );

        return $label === ''
            ? $name.' (unknown country)'
            : $name.' (unknown country: '.$label.')';
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
