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
 * including ones a test author placed there on purpose, like the comma
 * inside the "[Tag1,Tag2]" line).
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

it('composes LOCATION from the OSM venue title and formatted address, omitting it entirely when both are missing', function (?string $osmName, ?string $osmAddress) {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'osm_name' => $osmName,
        'osm_address' => $osmAddress,
    ]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $rawLocation = collect([$osmName, $osmAddress])->filter()->implode(', ');

    if ($rawLocation === '') {
        expect($ics)->not->toContain('LOCATION');
    } else {
        expect($ics)->toContain('LOCATION:'.escapeIcsText($rawLocation));
    }
})->with([
    'both values' => ['Café Central', 'Marktplatz 1, 99084 Erfurt'],
    'only the venue title' => ['Café Central', null],
    'only the address' => [null, 'Marktplatz 1, 99084 Erfurt'],
    'neither value' => [null, null],
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
    ]);

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $event = explode('BEGIN:VEVENT', $ics)[1];

    expect($event)
        ->not->toContain('URL:')
        ->not->toContain('DESCRIPTION:')
        ->not->toContain('LOCATION:')
        ->not->toContain('TBA');
});

it('puts tags as a [Tag1,Tag2] first line and keeps paragraph breaks in the rest of the description', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'description' => "Erster Absatz.\n\nZweiter Absatz.",
    ]);
    $event->attachTag(Tag::factory()->named(['de' => 'Bitcoin', 'en' => 'Bitcoin'])->ofType('meetup_event')->create());
    $event->attachTag(Tag::factory()->named(['de' => 'Meetup', 'en' => 'Meetup'])->ofType('meetup_event')->create());

    $ics = unfoldIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $rawDescription = "[Bitcoin,Meetup]\n\nErster Absatz.\n\nZweiter Absatz.";

    expect($ics)->toContain('DESCRIPTION:'.escapeIcsText($rawDescription));
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

it('falls back to the domain default instead of erroring on an unknown or malformed country, language or timezone', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    $response = test()->get('http://portal.einundzwanzig.space/stream-calendar?country=zz&language=xx&timezone=Not%2FARealZone');

    $response->assertSuccessful();

    $ics = unfoldIcs($response->getContent());

    expect($ics)
        ->toContain('X-WR-CALNAME:EINUNDZWANZIG Portal')
        ->toContain('TZID:Europe/Berlin')
        ->toContain('DTSTART;TZID=Europe/Berlin:');
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
