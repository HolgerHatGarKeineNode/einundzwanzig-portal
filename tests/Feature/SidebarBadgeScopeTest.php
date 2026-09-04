<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;

/*
|--------------------------------------------------------------------------
| Sidebar badges: the country scope, and the casing it is compared with
|--------------------------------------------------------------------------
|
| Issue #58. The country-scoped counters in
| components/layouts/app/sidebar.blade.php compare the country code with a
| plain `->where('countries.code', $navCountry)`, while the region lookup a
| few lines above it deliberately uses `whereRaw('LOWER(code) = ?')`. The
| stored codes are UPPERCASE — CountryFactory's own table says 'DE', 'AT',
| 'CH', 'US' — and the URL segment is lowercase. Every country-scoped badge
| therefore renders 0, including for a country that has content. A badge
| saying 0 is not a missing feature; it is a wrong statement to an organiser
| about their own country.
|
| Why the existing sidebar tests never caught it: every one of them seeds
| `Country::factory()->create(['code' => 'de'])` — lowercase, overriding the
| factory default. Under that fixture the case-sensitive comparison happens
| to work. The casing of the fixture is a measurement axis here, not a
| detail, so both directions are exercised below.
|
| Issue #51 additionally gives the country map entry its own badge, so that
| `Karte 🇨🇿 0` next to `Welt-Karte 🌐 307` explains a deliberately
| country-scoped map by itself. The pair only explains anything if the two
| numbers can differ, so the fixture makes them differ.
|
| Anchoring: `sidebarBadgeFor()` cuts the `<a href="...">` of one entry out
| of the document first and only then looks for the badge inside it. The
| badge text is a bare number that also occurs in other badges, breadcrumbs
| and page bodies — a plain regex for the number alone would match
| something else. Same approach as SidebarRegionBadgeCountTest (that file
| already owns the name `badgeFor`, hence the longer name here).
|
| Carrier page: /<country>/services. It renders the sidebar but lists
| services only — it never renders meetup, city, course or lecturer counts
| of its own, so nothing on the page can accidentally satisfy an assertion.
|
| Host: portal.einundzwanzig.space is requested explicitly. It is
| DomainMiddleware's fallback domain and carries NO region, so
| country_or_region_route() stays on the plain country routes and the hrefs
| below are the ones actually rendered. Using the ambient APP_URL host
| instead would make the expected hrefs depend on a .env value.
|
*/

function sidebarBadgeHost(): string
{
    return 'http://portal.einundzwanzig.space';
}

/**
 * The badge text of exactly one sidebar entry, addressed by its href.
 *
 * Returns null when the entry exists without a badge (or not at all) — that
 * is a different statement from an entry whose badge reads '0', and the
 * tests below rely on being able to tell those two apart.
 */
function sidebarBadgeFor(string $html, string $href): ?string
{
    if (! preg_match('/<a\s+href="'.preg_quote($href, '/').'".*?<\/a>/s', $html, $anchor)) {
        return null;
    }

    if (! preg_match('/data-flux-navlist-badge[^>]*>([^<]*)</', $anchor[0], $badge)) {
        return null;
    }

    return trim($badge[1]);
}

/**
 * Every badge of interest on one country page, keyed by the route it links to.
 *
 * @return array<string, string|null>
 */
function sidebarBadgesOn(string $html, string $segment): array
{
    $base = sidebarBadgeHost().'/'.$segment;

    return [
        'meetups' => sidebarBadgeFor($html, $base.'/meetups'),
        'all-meetups' => sidebarBadgeFor($html, $base.'/all-meetups'),
        'map' => sidebarBadgeFor($html, $base.'/map'),
        'map-world' => sidebarBadgeFor($html, $base.'/map-world'),
        'courses' => sidebarBadgeFor($html, $base.'/courses'),
        'lecturers' => sidebarBadgeFor($html, $base.'/lecturers'),
        'cities' => sidebarBadgeFor($html, $base.'/cities'),
    ];
}

function sidebarBadgeScopePage(string $path): string
{
    return test()->get(sidebarBadgeHost().'/'.$path)->assertOk()->getContent();
}

function sidebarBadgePage(string $segment): string
{
    return sidebarBadgeScopePage($segment.'/services');
}

/**
 * The marker set the map component actually shipped to the page.
 *
 * `@js($meetups)` in livewire/meetups/map.blade.php renders as
 * `markers: JSON.parse('…')` with every quote hex-escaped, and — for an empty
 * result — as the literal `markers: []`. Both forms were measured on the
 * rendered page, not inferred from Laravel's Js helper.
 *
 * null is a deliberate THIRD outcome next to `[]` and a filled array: "this
 * page shipped no marker set at all" (component moved, render changed, the
 * anchor rotted) must never be readable as "this country has no meetups".
 *
 * @return array<int, array<string, mixed>>|null
 */
function meetupMapMarkers(string $html): ?array
{
    $pattern = '/markers:\s*(?:\[\s*\]|JSON\.parse\(\s*\x27(?<payload>[^\x27]*)\x27\s*\))/';

    if (! preg_match($pattern, $html, $match)) {
        return null;
    }

    if (($match['payload'] ?? '') === '') {
        return [];
    }

    // Two layers: the JS string literal first, the JSON it carries second.
    $json = json_decode('"'.$match['payload'].'"');
    $markers = is_string($json) ? json_decode($json, true) : null;

    return is_array($markers) ? $markers : null;
}

/**
 * The meetups a list page actually rendered, by slug, sorted.
 *
 * Each row links to `meetups.landingpage` more than once (avatar, name, and
 * the event cell), hence the uniqueness.
 *
 * `/{country}/meetup/` is not only the landing page: the calendar stream
 * picker on the very same page links `/{country}/meetup/stream-calendar`
 * (route `ics-meetup`), which is not a meetup. Intersecting with the slugs
 * that actually exist drops it — and it does NOT hide an over-inclusive
 * list, because a page that failed to filter carries the other country's
 * real slugs, and those survive the intersection.
 *
 * @return array<int, string>
 */
function meetupSlugsListedOn(string $html, string $segment): array
{
    preg_match_all('#/'.preg_quote($segment, '#').'/meetup/([a-z0-9\-]+)#', $html, $matches);

    $slugs = array_values(array_unique(array_intersect(
        $matches[1],
        Meetup::query()->pluck('slug')->all()
    )));
    sort($slugs);

    return $slugs;
}

/**
 * @param  iterable<int, string>  $slugs
 * @return array<int, string>
 */
function meetupSlugsSorted(iterable $slugs): array
{
    $sorted = collect($slugs)->all();
    sort($sorted);

    return $sorted;
}

/**
 * Three countries, and no two numbers in this fixture are the same.
 *
 * That is the point of it: a badge that ignores the country scope entirely
 * and counts the whole table has to produce a DIFFERENT number from the
 * correct one for every single entry, otherwise the test would pass on a
 * counter that never scoped anything. Per entry, "home country" vs "whole
 * table" is 2 vs 7 meetups, 3 vs 6 cities, 2 vs 5 courses, 1 vs 4
 * lecturers. (Six cities, not five: the third country owns one too.)
 *
 * The home country's two courses share ONE lecturer on purpose, so the
 * course badge and the lecturer badge cannot be confused with each other
 * either.
 *
 * The third country holds a city but nothing else — the state the reporter
 * of #51 was actually looking at.
 *
 * The casing of the three stored codes is the parameter; the URL segments
 * are always lowercase, because that is what the portal's URLs are.
 *
 * @return array{home: Country, other: Country, empty: Country, homeMeetupSlugs: array<int, string>, otherMeetupSlugs: array<int, string>}
 */
function sidebarBadgeScopeSeed(string $homeCode, string $otherCode, string $emptyCode): array
{
    $home = Country::factory()->create(['name' => 'Deutschland', 'english_name' => 'Germany', 'code' => $homeCode]);
    $other = Country::factory()->create(['name' => 'Österreich', 'english_name' => 'Austria', 'code' => $otherCode]);
    $empty = Country::factory()->create(['name' => 'Schweiz', 'english_name' => 'Switzerland', 'code' => $emptyCode]);

    $homeCities = City::factory()->count(3)->create(['country_id' => $home->id, 'region_id' => null]);
    $otherCities = City::factory()->count(2)->create(['country_id' => $other->id, 'region_id' => null]);
    City::factory()->create(['country_id' => $empty->id, 'region_id' => null]);

    $homeMeetups = Meetup::factory()->count(2)->create(['city_id' => $homeCities->first()->id]);
    $otherMeetups = Meetup::factory()->count(5)->create(['city_id' => $otherCities->first()->id]);

    $homeLecturer = Lecturer::factory()->create();
    foreach (range(1, 2) as $ignored) {
        CourseEvent::factory()->create([
            'course_id' => Course::factory()->create(['lecturer_id' => $homeLecturer->id])->id,
            'city_id' => $homeCities->first()->id,
        ]);
    }

    // Three courses, each with its own lecturer from CourseFactory.
    foreach (range(1, 3) as $ignored) {
        CourseEvent::factory()->create([
            'course_id' => Course::factory()->create()->id,
            'city_id' => $otherCities->first()->id,
        ]);
    }

    return [
        'home' => $home,
        'other' => $other,
        'empty' => $empty,
        'homeMeetupSlugs' => meetupSlugsSorted($homeMeetups->pluck('slug')),
        'otherMeetupSlugs' => meetupSlugsSorted($otherMeetups->pluck('slug')),
    ];
}

/**
 * Both stored casings, for every case that renders a real page.
 *
 * Uppercase is the direction that catches issue #58. Lowercase is not
 * decoration: it catches the over-correction — a fix that lowercases the
 * column but uppercases the segment, or the other way round, would pass the
 * uppercase case and break every installation whose codes are already
 * lowercase (the portal's own database holds both, 'DE' next to 'us').
 */
dataset('stored country code casing', [
    'stored uppercase' => [['DE', 'AT', 'CH']],
    'stored lowercase' => [['de', 'at', 'ch']],
]);

/*
|--------------------------------------------------------------------------
| 1 + 2 — the casing, in both directions, against a fixture that scopes
|--------------------------------------------------------------------------
*/

it('counts the country when its stored code is uppercase and the url segment is not', function () {
    sidebarBadgeScopeSeed('DE', 'AT', 'CH');

    $badges = sidebarBadgesOn(sidebarBadgePage('de'), 'de');

    expect($badges)->toMatchArray([
        // Country-scoped: the home country's own rows, never the whole table.
        'meetups' => '2',
        'courses' => '2',
        'lecturers' => '1',
        'cities' => '3',
        // Global on purpose — these two SHOULD count everything.
        'all-meetups' => '7',
        'map-world' => '7',
    ]);
});

it('still counts the country when its stored code is already lowercase', function () {
    sidebarBadgeScopeSeed('de', 'at', 'ch');

    $badges = sidebarBadgesOn(sidebarBadgePage('de'), 'de');

    expect($badges)->toMatchArray([
        'meetups' => '2',
        'courses' => '2',
        'lecturers' => '1',
        'cities' => '3',
        'all-meetups' => '7',
        'map-world' => '7',
    ]);
});

/*
|--------------------------------------------------------------------------
| Negative control for the two cases above
|--------------------------------------------------------------------------
|
| The uppercase fixture only guards anything as long as the test database
| really does compare strings case-sensitively. It does on SQLite; on MySQL
| with a utf8mb4_*_ci collation `where('countries.code', 'de')` matches a
| stored 'DE' all by itself. On such a connection the two tests above would
| stay green against the very code they exist to reject, and nobody would
| notice — the badge would be broken in exactly one place: production.
|
| So this measures the assumption instead of trusting it: over the same
| fixture, the shape issue #58 reported (plain equality) has to collapse to
| 0 while the case-insensitive shape returns the real 2, and the rendered
| badge has to follow the second one.
|
| WHAT IT GUARDS, EXACTLY — and what it does not. It goes red when the
| CONNECTION stops discriminating (a `_ci` collation), and it goes red when
| someone lowercases the seed literal on the line right below it. It does
| NOT cover the `stored country code casing` dataset further down: tidy that
| dataset to lowercase-only and this control stays green while all eight
| dataset cases quietly stop discriminating. The dataset carries its own
| reason in its own docblock for that reason — a control cannot guard a
| fixture it never reads.
|
*/

it('keeps the uppercase fixture discriminating between the two comparison shapes', function () {
    sidebarBadgeScopeSeed('DE', 'AT', 'CH');

    $reportedShape = Meetup::query()
        ->whereHas('city.country', fn ($query) => $query->where('countries.code', 'de'))
        ->count();

    // The map page spelled the very same comparison without qualifying the
    // column (`->where('code', $this->country)`). Same semantics, same trap —
    // both spellings that were actually in production are pinned here, so the
    // control does not silently cover only one of them.
    $reportedShapeUnqualified = Meetup::query()
        ->whereHas('city.country', fn ($query) => $query->where('code', 'de'))
        ->count();

    $caseInsensitiveShape = Meetup::query()
        ->whereHas('city.country', fn ($query) => $query->whereRaw('LOWER(countries.code) = ?', ['de']))
        ->count();

    expect($reportedShape)->toBe(0)
        ->and($reportedShapeUnqualified)->toBe(0)
        ->and($caseInsensitiveShape)->toBe(2)
        ->and(sidebarBadgesOn(sidebarBadgePage('de'), 'de')['meetups'])->toBe('2');
});

/*
|--------------------------------------------------------------------------
| 3 — the pair issue #51 wants a reader to compare
|--------------------------------------------------------------------------
|
| The country map entry carries the count of the country it links to, the
| world map entry the count of everything. Asserting that they differ is
| part of the case: two equal numbers would explain nothing, and the whole
| point of the badge is to replace the explanatory prose the owner rejected.
|
*/

it('gives the country map its own scoped count next to the global world map count', function () {
    sidebarBadgeScopeSeed('DE', 'AT', 'CH');

    $badges = sidebarBadgesOn(sidebarBadgePage('de'), 'de');

    expect($badges['map'])->toBe('2')
        ->and($badges['map-world'])->toBe('7')
        ->and($badges['map'])->not->toBe($badges['map-world']);
});

/*
|--------------------------------------------------------------------------
| 4 — zero is an answer, not a failure
|--------------------------------------------------------------------------
|
| The country the reporter of #51 was on: nothing of its own, while the
| world clearly has something. Both statements have to be true at the same
| time and both have to be rendered — a missing badge (null) is not the
| same as a badge reading '0', so the assertions are on the string.
|
*/

it('renders a truthful zero for a country without meetups while the world entries keep the total', function () {
    sidebarBadgeScopeSeed('DE', 'AT', 'CH');

    $badges = sidebarBadgesOn(sidebarBadgePage('ch'), 'ch');

    expect($badges)->toMatchArray([
        'meetups' => '0',
        'map' => '0',
        'courses' => '0',
        'lecturers' => '0',
        // It does have one city — "no meetups" must not be smeared over
        // everything else the country owns.
        'cities' => '1',
        'all-meetups' => '7',
        'map-world' => '7',
    ]);
});

/*
|--------------------------------------------------------------------------
| 5 — the pages behind the links, not only the badges beside them
|--------------------------------------------------------------------------
|
| The same case-sensitive comparison sits on the pages themselves:
| livewire/meetups/map.blade.php filtered its markers with
| `->where('code', $this->country)` and livewire/meetups/index.blade.php its
| rows with `->where('countries.code', $this->country)`. Measured by the
| author of the sidebar fix: with a stored 'US' and the route /us/map the map
| drew ZERO markers while the freshly corrected sidebar badge said 3.
|
| That is the worse half of issue #58. A badge showing 0 is a wrong number; a
| badge showing 3 next to an empty map is a wrong number AND a broken
| promise, and the organiser has no way to tell which of the two lies.
|
| These cases therefore assert on the server-rendered payload of the page
| itself, not on the sidebar of that page.
|
*/

it('plots only the country meetups on its own map page', function (array $codes) {
    $seed = sidebarBadgeScopeSeed(...$codes);

    $markers = meetupMapMarkers(sidebarBadgeScopePage('de/map'));

    expect($markers)->not->toBeNull()
        ->and(meetupSlugsSorted(collect($markers)->pluck('slug')))->toBe($seed['homeMeetupSlugs'])
        ->and($markers)->toHaveCount(2);
})->with('stored country code casing');

it('lists only the country meetups on the meetups page', function (array $codes) {
    $seed = sidebarBadgeScopeSeed(...$codes);

    expect(meetupSlugsListedOn(sidebarBadgeScopePage('de/meetups'), 'de'))
        ->toBe($seed['homeMeetupSlugs']);
})->with('stored country code casing');

/*
|--------------------------------------------------------------------------
| 6 — the badge and the page it links to have to be the SAME number
|--------------------------------------------------------------------------
|
| This is the case issue #51 actually rests on. `Karte 🇨🇿 0` next to
| `Welt-Karte 🌐 307` only explains a country-scoped map if the 0 is the
| number of markers a reader finds after clicking. The moment the two
| numbers come from two differently-spelled filters, the badge stops being
| an explanation and becomes a second, contradicting claim.
|
| Asserted against EACH OTHER, so the pair cannot drift apart while both
| halves still match some constant a test author wrote down. The second
| expectation is the other half of that trap: "0 equals 0" satisfies
| agreement perfectly on a badge that counts nothing and a page that filters
| everything away — which is exactly the state master was in. Agreement is
| only worth asserting together with the number both sides owe the database.
|
*/

it('keeps the sidebar map badge and the markers on the map it links to the same number', function (array $codes) {
    sidebarBadgeScopeSeed(...$codes);

    $badge = sidebarBadgesOn(sidebarBadgePage('de'), 'de')['map'];
    $markers = meetupMapMarkers(sidebarBadgeScopePage('de/map'));

    expect($markers)->not->toBeNull()
        ->and((string) count($markers))->toBe($badge)
        ->and($badge)->toBe('2');
})->with('stored country code casing');

/*
|--------------------------------------------------------------------------
| 7 — zero on the page, too, and still distinguishable from a broken filter
|--------------------------------------------------------------------------
*/

it('renders an empty map and a zero badge for a country without meetups', function (array $codes) {
    $seed = sidebarBadgeScopeSeed(...$codes);

    $html = sidebarBadgeScopePage('ch/map');

    // `[]`, not null: the page DID ship a marker set and it is empty. A null
    // here would mean the anchor no longer finds the payload, which must not
    // be allowed to pass as "this country has no meetups".
    expect(meetupMapMarkers($html))->toBe([])
        ->and(sidebarBadgesOn(sidebarBadgePage('ch'), 'ch')['map'])->toBe('0');

    // ... and it is empty because the filter WORKED, not because the page
    // rendered nothing: the other countries' meetups exist and must be
    // absent from this page by name.
    foreach ([...$seed['homeMeetupSlugs'], ...$seed['otherMeetupSlugs']] as $foreignSlug) {
        expect($html)->not->toContain($foreignSlug);
    }
})->with('stored country code casing');
