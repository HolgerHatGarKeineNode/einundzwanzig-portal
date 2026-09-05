<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;

/*
 * The iCalendar generator folds any line beyond 75 octets by inserting "\r\n "
 * (RFC 5545 §3.1). Assertions here read logical property values, so folded
 * lines are unwrapped first — otherwise a folded value would never match a
 * plain substring check.
 */
function unfoldIcs(string $ics): string
{
    return preg_replace("/\r\n[ \t]/", '', $ics);
}

/*
 * Mirrors Spatie\IcalendarGenerator\Properties\TextProperty::getValue() so
 * assertions can compute the expected escaped wire value from a raw string
 * instead of hand-typing escape sequences (an easy way to assert the wrong
 * thing, since RFC 5545 escapes ANY comma or semicolon in a TEXT value —
 * including ones a test author placed there on purpose, like the commas
 * inside a formatted OSM address).
 */
function escapeIcsText(string $text): string
{
    return str_replace(
        ['\\', '"', ',', ';', "\n"],
        ['\\\\', '\\"', '\\,', '\\;', '\\n'],
        $text
    );
}

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('derives the calendar name and timezone from the requesting domain (D1/D2)', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    $einundzwanzig = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $bitcoinIndiana = unfoldIcs(test()->get('http://portal.bitcoindiana.org/stream-calendar')->getContent());

    expect($einundzwanzig)
        ->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal')
        ->toContain('TZID:Europe/Berlin')
        ->toContain('DTSTART;TZID=Europe/Berlin:');

    expect($bitcoinIndiana)
        ->toContain('X-WR-CALNAME:Bitcoin Indiana')
        ->toContain('TZID:America/Indiana/Indianapolis')
        ->toContain('DTSTART;TZID=America/Indiana/Indianapolis:');
});

it('uses the event title as SUMMARY and falls back to the meetup name only when no title is set', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Bitcoin Meetup Erfurt']);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'Einsteigerabend',
        'start' => now()->addWeek(),
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => null,
        'start' => now()->addWeeks(2),
    ]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    expect($ics)
        ->toContain('SUMMARY:Einsteigerabend')
        ->toContain('SUMMARY:Bitcoin Meetup Erfurt')
        ->not->toContain('SUMMARY:Bitcoin Meetup Erfurt Einsteigerabend');
});

it('includes the end time when the source provides one and omits it otherwise', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
        'end' => now()->addWeek()->setTime(21, 0),
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeeks(2)->setTime(19, 0),
        'end' => null,
    ]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $events = collect(explode('BEGIN:VEVENT', $ics))->skip(1);

    expect($events->filter(fn ($event): bool => str_contains($event, 'DTEND')))->toHaveCount(1);
});

/*
 * The expected value is asserted WITH its terminating CRLF (the response is
 * unfolded first, so a CRLF that is still there marks the end of the logical
 * value). A plain `toContain('LOCATION:Café Central\, Marktplatz 1')` would
 * also pass if the controller appended the free-text column to the OSM pair —
 * the CRLF is what makes "and nothing else" assertable.
 */
it('prefers the OSM venue for LOCATION, falls back to the free-text location and omits the property when neither exists', function (?string $osmName, ?string $osmAddress, ?string $freeText, ?string $expected) {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'osm_name' => $osmName,
        'osm_address' => $osmAddress,
        'location' => $freeText,
    ]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    if ($expected === null) {
        expect($ics)->not->toContain('LOCATION');
    } else {
        expect($ics)->toContain('LOCATION:'.escapeIcsText($expected)."\r\n");
    }
})->with([
    'osm title and address' => ['Café Central', 'Marktplatz 1, 99084 Erfurt', null, 'Café Central, Marktplatz 1, 99084 Erfurt'],
    'only the osm venue title' => ['Café Central', null, null, 'Café Central'],
    'only the osm address' => [null, 'Marktplatz 1, 99084 Erfurt', null, 'Marktplatz 1, 99084 Erfurt'],
    // Issue #36's own sample row: free text, all six osm_* columns null. This is
    // the majority shape in the table, and 8e4f1be5 dropped it from the feed.
    'only the free-text location' => [null, null, 'Schwabach', 'Schwabach'],
    // OSM data wins, and the free text is not appended to it.
    'osm data alongside free text' => ['Café Central', 'Marktplatz 1, 99084 Erfurt', 'Schwabach', 'Café Central, Marktplatz 1, 99084 Erfurt'],
    'neither value' => [null, null, null, null],
]);

it('omits optional properties instead of placeholders when the source has no value', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'link' => null,
        'description' => null,
        'osm_name' => null,
        'osm_address' => null,
        // The factory fills `location` with a fake address by default; LOCATION
        // now falls back to that column, so an "everything empty" event has to
        // clear it explicitly.
        'location' => null,
    ]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $event = explode('BEGIN:VEVENT', $ics)[1];

    expect($event)
        ->not->toContain('URL:')
        ->not->toContain('DESCRIPTION:')
        ->not->toContain('LOCATION:')
        ->not->toContain('TBA');
});

it('puts tags as a [Tag1] [Tag2] first line and keeps paragraph breaks in the rest of the description', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'description' => "Erster Absatz.\n\nZweiter Absatz.",
    ]);
    $event->attachTag(Tag::factory()->named(['de' => 'Bitcoin', 'en' => 'Bitcoin'])->ofType('meetup_event')->create());
    $event->attachTag(Tag::factory()->named(['de' => 'Meetup', 'en' => 'Meetup'])->ofType('meetup_event')->create());

    $raw = test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent();
    $ics = unfoldIcs($raw);
    $rawDescription = "[Bitcoin] [Meetup]\n\nErster Absatz.\n\nZweiter Absatz.";

    expect($ics)
        ->toContain('DESCRIPTION:'.escapeIcsText($rawDescription))
        // The separator is now a space, and a space is not in RFC 5545's escape
        // set — so the tag line reaches the wire verbatim. The previous "[A,B]"
        // shape was emitted as "[Bitcoin\,Meetup]"; asserting the literal here
        // proves the escape sequence is gone rather than merely relocated.
        ->toContain('DESCRIPTION:[Bitcoin] [Meetup]')
        ->not->toContain('[Bitcoin\\,Meetup]');

    // Folding is unchanged too. Asserted against the RAW response, where a fold
    // would appear as "\r\n " mid-value: the whole property is 67 octets (it was
    // 66 as "[Bitcoin\,Meetup]" — one escape sequence traded for one space and
    // one bracket pair), so it stays under RFC 5545's 75-octet limit and still
    // arrives as a single physical line.
    expect($raw)->toContain("DESCRIPTION:[Bitcoin] [Meetup]\\n\\nErster Absatz.\\n\\nZweiter Absatz.\r\n");
});

it('escapes commas, semicolons and backslashes per RFC 5545 and folds long lines', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $osmName = 'Bar; Ecke\\Nord'; // raw value: Bar; Ecke\Nord
    $longAddress = str_repeat('Sehr lange Adresse mit vielen Zeichen, ', 5).'Ende';
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'osm_name' => $osmName,
        'osm_address' => $longAddress,
    ]);

    $raw = test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent();

    // Folded: the raw response must contain a fold sequence for the long LOCATION value.
    expect($raw)->toContain("\r\n ");

    $unfolded = unfoldIcs($raw);
    $rawLocation = $osmName.', '.$longAddress;

    expect($unfolded)->toContain('LOCATION:'.escapeIcsText($rawLocation));
});

it('lets an explicit language and timezone selection override the requesting domain (Issue e5233554)', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    // Domain is the German one — without the new parameters this would be
    // "EINUNDZWANZIG Portal" / Europe/Berlin, exactly like the first test above.
    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?language=cs&timezone=Europe/Prague')->getContent());

    expect($ics)
        ->toContain('X-WR-CALNAME:Jednadvacet')
        ->toContain('TZID:Europe/Prague')
        ->toContain('DTSTART;TZID=Europe/Prague:');
});

it('translates tag names into the requested language instead of the domain locale', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);
    $event->attachTag(Tag::factory()->named(['de' => 'Bitcoin-Stammtisch', 'cs' => 'Bitcoin setkání'])->ofType('meetup_event')->create());

    $default = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $czech = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?language=cs')->getContent());

    expect($default)->toContain('[Bitcoin-Stammtisch]');
    expect($czech)->toContain('[Bitcoin setkání]');
});

it('leaves the feed content unfiltered by country when no country parameter is present (no regression)', function () {
    $czechCountry = Country::factory()->create(['code' => 'cz']);
    $czechCity = City::factory()->create(['country_id' => $czechCountry->id]);

    $germanMeetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'German Meetup']);
    $czechMeetup = Meetup::factory()->create(['city_id' => $czechCity->id, 'name' => 'Czech Meetup']);
    MeetupEvent::factory()->create(['meetup_id' => $germanMeetup->id, 'title' => 'German Event', 'start' => now()->addWeek()]);
    MeetupEvent::factory()->create(['meetup_id' => $czechMeetup->id, 'title' => 'Czech Event', 'start' => now()->addWeek()]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    expect($ics)->toContain('SUMMARY:German Event')->toContain('SUMMARY:Czech Event');
});

it('scopes the feed content to the selected country when the country parameter is present', function () {
    $czechCountry = Country::factory()->create(['code' => 'cz']);
    $czechCity = City::factory()->create(['country_id' => $czechCountry->id]);

    $germanMeetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'German Meetup']);
    $czechMeetup = Meetup::factory()->create(['city_id' => $czechCity->id, 'name' => 'Czech Meetup']);
    MeetupEvent::factory()->create(['meetup_id' => $germanMeetup->id, 'title' => 'German Event', 'start' => now()->addWeek()]);
    MeetupEvent::factory()->create(['meetup_id' => $czechMeetup->id, 'title' => 'Czech Event', 'start' => now()->addWeek()]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?country=cz')->getContent());

    expect($ics)->toContain('SUMMARY:Czech Event')->not->toContain('SUMMARY:German Event');
});

it('falls back to the domain default instead of erroring on an unknown or malformed language or timezone', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    // No `country` here on purpose: since #77 an unrecognized country is not a fallback
    // case at all — it empties the feed and renames the calendar, which would drown out
    // what this test is about. That case has its own two tests below.
    $response = test()->get('http://portal.einundzwanzig.space/stream-calendar?language=xx&timezone=Not%2FARealZone');

    $response->assertSuccessful();

    $ics = unfoldIcs($response->getContent());

    expect($ics)
        ->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal')
        ->toContain('TZID:Europe/Berlin')
        ->toContain('DTSTART;TZID=Europe/Berlin:');
});

it('matches nothing — not every country — for an unknown or malformed country value', function () {
    $czechCountry = Country::factory()->create(['code' => 'cz']);
    $czechCity = City::factory()->create(['country_id' => $czechCountry->id]);
    $austrianCountry = Country::factory()->create(['code' => 'at']);
    $austrianCity = City::factory()->create(['country_id' => $austrianCountry->id]);

    $germanMeetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'German Meetup']);
    $czechMeetup = Meetup::factory()->create(['city_id' => $czechCity->id, 'name' => 'Czech Meetup']);
    $austrianMeetup = Meetup::factory()->create(['city_id' => $austrianCity->id, 'name' => 'Austrian Meetup']);

    // Two German events among five — the sample issue #77 measured the old behavior
    // against (?country=zz -> 5, ?country=d -> 5, ?country=de -> 2).
    MeetupEvent::factory()->count(2)->create(['meetup_id' => $germanMeetup->id, 'start' => now()->addWeek()]);
    MeetupEvent::factory()->count(2)->create(['meetup_id' => $czechMeetup->id, 'start' => now()->addWeek()]);
    MeetupEvent::factory()->create(['meetup_id' => $austrianMeetup->id, 'start' => now()->addWeek()]);

    $countEvents = fn (string $query): int => substr_count(
        unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar'.$query)->getContent()),
        'BEGIN:VEVENT'
    );

    /*
     * The premise this file carried until #77 was that "an unrecognized value is the
     * same as no country parameter at all", and that was the defect: a `country=` that
     * is present states the intent to narrow, so answering it with the whole world
     * delivers MORE than was asked for — and nobody re-reads a calendar subscription,
     * so over-delivery is never reported. "zz" matches no Country row and "d" is a
     * prefix of "de" that matches none either; both now match nothing.
     *
     * What has NOT changed is the other direction: an unrecognized value must still not
     * fall back to the domain default. The domain serving this request
     * (portal.einundzwanzig.space) defaults to "de" — a fallback would answer
     * `?country=zz` with the two German events, i.e. silently serve a stale or typo'd
     * URL some other country's calendar. Counting is the load-bearing assertion here:
     * "0 vs. 2 vs. 5" is precisely the observable, and the unfiltered count guards that
     * an empty feed really is the filter's doing and not an empty database.
     */
    expect([
        'country=zz' => $countEvents('?country=zz'),
        'country=d' => $countEvents('?country=d'),
        'country=de' => $countEvents('?country=de'),
        'no country' => $countEvents(''),
    ])->toBe([
        'country=zz' => 0,
        'country=d' => 0,
        'country=de' => 2,
        'no country' => 5,
    ]);
});

it('names the unrecognized country in X-WR-CALNAME so an empty feed is not mistaken for a broken portal', function () {
    $czechCountry = Country::factory()->create(['code' => 'cz']);
    $czechCity = City::factory()->create(['country_id' => $czechCountry->id]);
    $czechMeetup = Meetup::factory()->create(['city_id' => $czechCity->id, 'name' => 'Czech Meetup']);
    MeetupEvent::factory()->create(['meetup_id' => $czechMeetup->id, 'title' => 'Czech Event', 'start' => now()->addWeek()]);

    $unknown = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?country=zz')->getContent());
    $knownButEmpty = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?country=de')->getContent());

    /*
     * Both feeds are empty, and X-WR-CALNAME is the only line a subscriber's client
     * shows for an empty calendar — so it has to carry the difference between "the
     * portal did not know the code you typed" and "your country simply has no upcoming
     * events". Without the marker the two are the same file, and either one reads like
     * a broken portal.
     */
    expect(substr_count($unknown, 'BEGIN:VEVENT'))->toBe(0)
        ->and(substr_count($knownButEmpty, 'BEGIN:VEVENT'))->toBe(0)
        ->and($unknown)->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal (unknown country: zz)')
        ->and($knownButEmpty)->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal')
        ->and($knownButEmpty)->not->toContain('unknown country');
});

it('cannot be made to inject or inflate an iCalendar line through the country parameter', function () {
    $injection = urlencode("zz\r\nSUMMARY:injected;,\\".str_repeat('x', 200));

    $ics = test()->get('http://portal.einundzwanzig.space/stream-calendar?country='.$injection)->getContent();
    $unfolded = unfoldIcs($ics);

    /*
     * `country` is user input that now reaches an output property, so it is stripped to
     * [a-z0-9-] and capped before it gets there — the escaping of the iCalendar
     * generator is a second line of defense, not the first. Asserting on the RAW ics as
     * well is deliberate: unfolding would hide a CRLF the value smuggled in, which is
     * exactly the injection this guards against.
     */
    expect($unfolded)->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal (unknown country: zzsummar)')
        ->and($unfolded)->not->toContain('SUMMARY:injected')
        ->and(substr_count($ics, 'X-WR-CALNAME'))->toBe(1)
        ->and(substr_count($ics, 'SUMMARY'))->toBe(0);

    // Nothing survives the strip: the marker still has to say that a country was
    // requested and not recognized, otherwise this value alone would look like a feed
    // that was never scoped at all.
    $stripped = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?country='.urlencode('/// '))->getContent());

    expect($stripped)->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal (unknown country)')
        ->and(substr_count($stripped, 'BEGIN:VEVENT'))->toBe(0);
});

it('keeps the UID stable across a rename, bumps SEQUENCE, and drops the event once it is cancelled (D-update/D-cancel)', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Bitcoin Meetup Erfurt']);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'Stammtisch',
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    $before = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    preg_match('/UID:(?<uid>\S+)/', $before, $uidBefore);
    preg_match('/SEQUENCE:(?<sequence>\d+)/', $before, $sequenceBefore);

    expect($uidBefore)->toHaveKey('uid')
        ->and($sequenceBefore)->toHaveKey('sequence');

    $this->travelTo(now()->addMinute());
    $event->update(['title' => 'Umbenannter Stammtisch']);
    $meetup->update(['name' => 'Bitcoin Meetup Erfurt e.V.']);

    $after = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    preg_match('/UID:(?<uid>\S+)/', $after, $uidAfter);
    preg_match('/SEQUENCE:(?<sequence>\d+)/', $after, $sequenceAfter);

    expect($uidAfter['uid'])->toBe($uidBefore['uid'])
        ->and((int) $sequenceAfter['sequence'])->toBeGreaterThan((int) $sequenceBefore['sequence'])
        ->and($after)->toContain('SUMMARY:Umbenannter Stammtisch');

    $event->delete();

    $cancelled = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    expect($cancelled)->not->toContain((string) $uidAfter['uid']);

    $this->travelBack();
});

/*
|--------------------------------------------------------------------------
| The gate in resolveCountryCode(), with the code stored the way it is stored
|--------------------------------------------------------------------------
|
| Every country case above seeds its codes LOWERCASE (the shared beforeEach and
| the three `?country=` cases). That is why none of them could fail while
| DownloadMeetupCalendar::resolveCountryCode() compared case-sensitively: with
| a lowercase row the broken gate happens to pass. CountryFactory's own default
| is uppercase, and production holds both spellings side by side.
|
| The failure direction is the dangerous one. The gate is not a filter — when
| it falls through it returns null and NO filter is applied at all, so a public
| subscription URL that asked for one country silently ships the whole world.
| A feed that delivers too much is the kind of defect nobody reports.
|
| Counting VEVENTs is therefore the load-bearing assertion: "one event too many"
| is precisely the observable, and a SUMMARY substring proves nothing on its own
| because a DESCRIPTION can carry the same text.
|
*/

it('scopes the feed to the selected country when the stored country code is uppercase', function () {
    // The beforeEach seeded this one lowercase; store it the way the database does.
    Country::whereKey($this->city->country_id)->update(['code' => 'DE']);

    $czechCountry = Country::factory()->create(['code' => 'CZ']);
    $czechCity = City::factory()->create(['country_id' => $czechCountry->id]);

    $germanMeetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'German Meetup']);
    $czechMeetup = Meetup::factory()->create(['city_id' => $czechCity->id, 'name' => 'Czech Meetup']);
    MeetupEvent::factory()->create(['meetup_id' => $germanMeetup->id, 'title' => 'German Event', 'start' => now()->addWeek()]);
    MeetupEvent::factory()->create(['meetup_id' => $czechMeetup->id, 'title' => 'Czech Event', 'start' => now()->addWeek()]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?country=de')->getContent());

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1)
        ->and($ics)->toContain('SUMMARY:German Event');
});
