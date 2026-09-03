<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;

/*
|--------------------------------------------------------------------------
| German text must not reach an English visitor — asserted on RENDERED HTML
|--------------------------------------------------------------------------
|
| LangKeyParityTest and LangCodeCoverageTest look at lang/*.json only. They
| stay green while the interface is German, in two ways neither of them can
| see:
|
|   1. German text hardcoded in a Blade file, outside __() — no key exists,
|      so there is nothing for a key test to miss. That is how "Webseite"
|      and "… Uhr" reached the English screenshots of 2026-09-03.
|   2. A __() key that is missing from EVERY lang/*.json. The key sets stay
|      identical (parity green) and Laravel echoes the key itself, which is
|      German: __(':time Uhr') renders "21:30 Uhr" for an English visitor.
|      Note that an EMPTY value has the same effect — Translator::get()
|      returns `$line ?: $key`, so "":time Uhr": """ falls back to the German
|      key too. That is exactly how lang/de.json is written here.
|
| So the assertion has to happen on what the page actually sends. Every test
| below performs a real HTTP request and searches the response body. A test
| in this file that can pass without a page having been rendered is the wrong
| test.
|
| Dates USED to be excluded here: App\Support\Carbon::asDate() and its
| siblings hardcoded ->locale('de'), so month and weekday names rendered in
| German for every visitor ("05. Januar 2027"). Listing them would have left
| this guard permanently red, and a permanently red guard gets switched off.
|
| That justification expired on 2026-09-03 with issue #48: every formatter now
| emits ISO 8601 ("2027-01-05") in every locale, so no month name may appear
| in a date on any page, in any language. The German month names are therefore
| in the watch list below — a reintroduced ->locale('de') puts "Oktober"
| straight onto an English page and this guard fires.
|
| The SHAPE of a date is not this file's business; tests/Feature/Lang/
| IsoDateFormatTest.php owns that, including the timezone conversion and the
| per-locale pattern. This file only watches for German words on an English
| page, and a month name is one.
*/

/**
 * German month names that cannot also be read as English.
 *
 * April, August, September and November are deliberately absent: they are
 * spelled identically in English, so watching for them would fire on
 * legitimate English output and the guard would be deleted rather than fixed.
 *
 * Weekday names are absent for a different reason — "Montag" and its siblings
 * turn up in ordinary German prose (recurrence wording, opening hours) far
 * more readily than a month name does, and the negative control below asserts
 * an EXACT word list per page, which such a word would have to be carried in
 * forever.
 *
 * Under ISO 8601 (issue #48) no date carries a month name in any locale, so
 * while the fix holds, none of these can appear on any page and the four
 * guards below pass them for free. That is precisely how this list was
 * decoration for one round: it needs the page-level negative control at the
 * bottom of this file, which binds the pre-#48 formatter and requires the
 * matcher to find the month again, plus the pinned fixture date that keeps a
 * WATCHED month on the page whatever day the suite runs.
 *
 * @var list<string>
 */
const RENDERED_GERMAN_MONTH_NAMES = ['Januar', 'Februar', 'März', 'Mai', 'Juni', 'Juli', 'Oktober', 'Dezember'];

/**
 * The German literals that were found in English screenshots on 2026-09-03,
 * plus the month names that issue #48 made watchable (see above).
 *
 * @var list<string>
 */
const RENDERED_GERMAN_LITERALS = ['Uhr', 'Webseite', ...RENDERED_GERMAN_MONTH_NAMES];

/**
 * The date formatter as it stood before issue #48 — hardcoded ->locale('de'),
 * copied from app/Support/Carbon.php at commit 336ccec.
 *
 * Bound through Date::use() in the negative control at the bottom, it puts a
 * German month name back onto the real pages, which is the only honest way to
 * show that the month names above are watched rather than merely listed. The
 * app is never touched, not even briefly: app/ and resources/ belong to
 * another author this round.
 *
 * IsoDateFormatTest carries the same fixture under its own name. Duplicated on
 * purpose rather than shared: a test file that is run alone
 * (`pest tests/Feature/Lang/RenderedLocaleTest.php`) does not have the other
 * file's declarations, and a guard that fatals depending on how the suite was
 * invoked is worse than twenty duplicated lines.
 */
class PreIsoGermanDate extends CarbonImmutable
{
    public function asDate(): string
    {
        $dt = $this->timezone(config('app.user-timezone'))->locale('de');

        return str($dt->day)->padLeft(2, '0').'. '.$dt->monthName.' '.$dt->year;
    }

    public function asTime(): string
    {
        return $this->timezone(config('app.user-timezone'))->locale('de')
            ->format('H:i');
    }

    public function asDateTime(): string
    {
        $dt = $this->timezone(config('app.user-timezone'))->locale('de');

        return sprintf('%s.%s.%s %s (%s)',
            str($dt->day)->padLeft(2, '0'),
            str($dt->month)->padLeft(2, '0'),
            $dt->year,
            $dt->format('H:i'),
            $dt->timezoneAbbreviatedName
        );
    }
}

/**
 * Returns those of $words that occur in $html as WHOLE words.
 *
 * Whole-word matching is the point, not an optimisation. A plain
 * str_contains($html, 'Uhr') reports a hit on the perfectly correct German-only
 * "Uhrzeit" and on any class or attribute that happens to embed the three
 * letters; the mirror-image mistake is a word such as 'Serie', whose substring
 * check fires on the correct English "Series" — a word this very release
 * added. Both directions produce a guard nobody trusts, and an untrusted guard
 * gets deleted rather than fixed.
 *
 * \b is not used: without /u it is ASCII-only, and even with /u PCRE keeps \w
 * ASCII unless UCP is on, so \bÖffnungszeit\b matches mid-word. The explicit
 * \p{L}\p{N} lookarounds hold for the accented words this list will grow.
 *
 * @param  list<string>  $words
 * @return list<string>
 */
function renderedGermanWords(string $html, array $words): array
{
    return array_values(array_filter(
        $words,
        fn (string $word): bool => preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u',
            $html
        ) === 1
    ));
}

/**
 * The date these fixtures use, pinned to a month this guard actually watches.
 *
 * `now()->addDays(7)` was here until 2026-09-04 and made the month-name half
 * of the guard calendar-dependent: on 2026-09-03 the fixture fell on
 * 2026-09-10, whose German month name is "September" — one of the four names
 * excluded for being spelled the same in English. Measured by the reviewer
 * with App\Support\Carbon genuinely reverted: all seven tests in this file
 * passed, and the same page with the event forced into October failed. A guard
 * that is silent for four months of the year, including the day it was
 * written, is decoration.
 *
 * So the month is chosen, not inherited: the 15th of the next month whose
 * German name is in RENDERED_GERMAN_MONTH_NAMES, always in the future — the
 * meetup landing page lists only `start >= now()`, and an event that dropped
 * out of the list would take the guard with it.
 *
 * The horizon is at most 98 days, measured over a thousand consecutive days
 * rather than reasoned about; the worst case is 9–14 July, where the search
 * starts at 15 August and has to step over both excluded summer names. The
 * test at the bottom of this file holds all three properties for every
 * calendar day, so this paragraph cannot quietly go stale.
 */
function watchedMonthFixtureDate(): CarbonImmutable
{
    $candidate = CarbonImmutable::now()->addDays(7);

    // Past the 15th, the 15th of THIS month would be in the past.
    $candidate = $candidate->day > 15 ? $candidate->addMonth()->day(15) : $candidate->day(15);

    while (! in_array($candidate->locale('de')->monthName, RENDERED_GERMAN_MONTH_NAMES, true)) {
        $candidate = $candidate->addMonth();
    }

    return $candidate->startOfDay();
}

/**
 * Requests $url the way a browser of $locale does, and returns the body.
 *
 * The locale comes from the Accept-Language header on purpose: that is the
 * path DomainMiddleware actually takes for a first-time visitor, and it is
 * where the English screenshots came from. Tests\TestCase blanks the synthetic
 * Accept-Language default Symfony injects, so this header is the only thing
 * speaking here.
 *
 * The getLocale() check is a positive control. Without it, a request that
 * silently stayed on the German domain default would still satisfy "no German
 * words" for the wrong reason — the words would simply be somewhere else.
 */
function renderAsVisitor(object $case, string $url, string $locale): string
{
    $header = match ($locale) {
        'en' => 'en-US,en;q=0.9',
        'de' => 'de-DE,de;q=0.9',
    };

    $response = $case->withHeaders(['Accept-Language' => $header])->get($url);

    $response->assertSuccessful();

    expect(app()->getLocale())->toBe($locale, "Request to {$url} did not run under the '{$locale}' locale.");

    return (string) $response->getContent();
}

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);

    $this->fixtureDate = watchedMonthFixtureDate();

    // Every free-text field these pages echo is set explicitly. The factories
    // fill them from fake(), and a random German paragraph is exactly the kind
    // of input that makes a text-matching guard flap.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Bitcoin Meetup Testhausen',
        'slug' => 'bitcoin-meetup-testhausen',
        'intro' => 'A regular gathering for bitcoiners.',
        'webpage' => 'https://example.com/meetup',
        'visible_on_map' => true,
    ]);

    $this->event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => $this->fixtureDate->setTime(19, 30),
        'location' => 'Main Street 1',
        'description' => 'An evening about bitcoin.',
        'link' => 'https://example.com/event',
        'recurrence_type' => null,
    ]);

    $this->course = Course::factory()->create([
        'lecturer_id' => Lecturer::factory()->create(['name' => 'Jane Doe'])->id,
        'name' => 'Bitcoin Basics',
        'description' => 'An introduction to bitcoin.',
    ]);

    CourseEvent::factory()->create([
        'course_id' => $this->course->id,
        'city_id' => $this->city->id,
        'from' => $this->fixtureDate->setTime(18, 0),
        'to' => $this->fixtureDate->setTime(20, 0),
        'location' => 'Main Street 1',
        'link' => 'https://example.com/course-event',
    ]);

    $this->meetupUrl = route('meetups.landingpage', ['country' => 'de', 'meetup' => $this->meetup->slug]);
    $this->eventUrl = route('meetups.landingpage-event', [
        'country' => 'de',
        'meetup' => $this->meetup->slug,
        'event' => $this->event->id,
    ]);
    $this->courseUrl = route('courses.landingpage', ['country' => 'de', 'course' => $this->course->id]);
    // The map popup (components/meetup-popup.blade.php) has no route of its own.
    // meetups.map renders it server-side and hands the finished markup to Leaflet
    // as `popupHtml` inside @js($meetups), so the map page IS the renderable
    // surface for that component — no browser needed.
    $this->mapUrl = route('meetups.map', ['country' => 'de']);
});

it('renders the meetup landing page without German literals for an English visitor', function () {
    $html = renderAsVisitor($this, $this->meetupUrl, 'en');

    expect(renderedGermanWords($html, RENDERED_GERMAN_LITERALS))
        ->toBe([], 'German words rendered at an English visitor on '.$this->meetupUrl);
});

it('renders the meetup event page without German literals for an English visitor', function () {
    $html = renderAsVisitor($this, $this->eventUrl, 'en');

    expect(renderedGermanWords($html, RENDERED_GERMAN_LITERALS))
        ->toBe([], 'German words rendered at an English visitor on '.$this->eventUrl);
});

it('renders the course landing page without German literals for an English visitor', function () {
    $html = renderAsVisitor($this, $this->courseUrl, 'en');

    expect(renderedGermanWords($html, RENDERED_GERMAN_LITERALS))
        ->toBe([], 'German words rendered at an English visitor on '.$this->courseUrl);
});

it('renders the map popup markup without German literals for an English visitor', function () {
    $html = renderAsVisitor($this, $this->mapUrl, 'en');

    // Without the popup in the body the assertion below would be green for the
    // wrong reason, and the map page would still return 200.
    expect($html)->toContain($this->meetup->name);

    expect(renderedGermanWords($html, RENDERED_GERMAN_LITERALS))
        ->toBe([], 'German words rendered at an English visitor on '.$this->mapUrl);
});

/*
|--------------------------------------------------------------------------
| Negative control — the four assertions above must be able to fail
|--------------------------------------------------------------------------
|
| Each surface is rendered a second time, changing nothing but the visitor's
| Accept-Language, and the very same matcher must now FIND the literals. This
| is what separates "the English page is translated" from "the page never
| rendered that region at all" — the second would make the tests above green
| for free, and no assertion on the English render can tell the two apart.
|
| It holds as long as German stays German: lang/de.json carries "" for these
| keys, which Laravel resolves back to the German key.
*/
it('finds the same German literals on the same pages for a German visitor', function () {
    $expected = [
        $this->meetupUrl => ['Uhr', 'Webseite'],
        $this->eventUrl => ['Uhr'],
        $this->courseUrl => ['Uhr'],
        $this->mapUrl => ['Uhr'],
    ];

    foreach ($expected as $url => $literals) {
        $html = renderAsVisitor($this, $url, 'de');

        expect(renderedGermanWords($html, RENDERED_GERMAN_LITERALS))
            ->toBe($literals, 'German render of '.$url.' no longer carries the words the English guard watches for — the guard above is now vacuous.');
    }
});

/*
 * Calibration of the matcher itself, on a fixture rather than on a page, so
 * the word-boundary rule keeps being exercised no matter what the pages do.
 */
it('flags a whole German word in rendered output and spares English words that contain one', function () {
    $html = Blade::render(
        '<p>{{ $time }} Uhr</p><p>Uhrzeit</p><p>Series</p><p>Website</p><div class="webseiten-link">x</div>',
        ['time' => '19:30']
    );

    expect(renderedGermanWords($html, ['Uhr', 'Webseite', 'Serie']))->toBe(['Uhr']);
});

/*
|--------------------------------------------------------------------------
| Negative control for the month names — on a REAL page, not on a fixture
|--------------------------------------------------------------------------
|
| The four guards above cannot demonstrate the month half of the list: while
| dates are ISO, no page carries a month name, so those eight entries pass for
| free on every run. The reviewer's measurement of 2026-09-04 showed what that
| is worth — with App\Support\Carbon reverted for real, this whole file stayed
| green, because the fixture happened to fall in September.
|
| So the regression is reproduced here instead: the pre-#48 formatter is bound
| over the same page and the same matcher must now find the month.
*/
it('pins the fixture into a watched month and into the future, on every calendar day', function () {
    /*
     * The defect this replaced was a fixture that happened to be fine on the
     * day it was written. Asserting the pinning for TODAY only would repeat
     * exactly that mistake, so every day of a leap year and the two years
     * after it is walked. Three properties, all of them load-bearing:
     * the month must be one the guard watches, the date must stay in the
     * future (the landing page lists `start >= now()` and would otherwise drop
     * the event, taking the guard with it), and the horizon must stay short
     * enough that the fixture is not silently a year away.
     */
    $offenders = [];
    $longestHorizonDays = 0;

    try {
        $firstDay = CarbonImmutable::create(2024, 1, 1, 12, 0, 0);

        for ($offset = 0; $offset < 1000; $offset++) {
            $today = $firstDay->addDays($offset);
            CarbonImmutable::setTestNow($today);

            $fixture = watchedMonthFixtureDate();
            $germanMonth = $fixture->locale('de')->monthName;
            $horizonDays = (int) $today->startOfDay()->diffInDays($fixture);
            $longestHorizonDays = max($longestHorizonDays, $horizonDays);

            $offenders[$today->toDateString()] = match (true) {
                ! in_array($germanMonth, RENDERED_GERMAN_MONTH_NAMES, true) => "excluded month {$germanMonth}",
                $fixture->lessThanOrEqualTo($today) => "not in the future: {$fixture->toDateString()}",
                default => null,
            };
        }
    } finally {
        CarbonImmutable::setTestNow();
    }

    expect(array_filter($offenders))->toBe([], 'watchedMonthFixtureDate() does not hold on these days.');

    /*
     * The horizon is asserted rather than described, because the first version
     * of the docblock above got it wrong: it claimed "at most two months",
     * and this loop measured 98 days. The worst case is the 9th to the 14th of
     * July — past the 15th of July, so the search starts at 15 August, and
     * August and September are both excluded names.
     */
    expect($longestHorizonDays)->toBe(98, 'The fixture horizon moved; the docblock above states 98 days.');
});

it('finds a German month name on the English page once the pre-issue-48 formatter is bound', function () {
    Date::use(PreIsoGermanDate::class);

    $watchedMonth = $this->fixtureDate->locale('de')->monthName;

    // Positive control on the pinning, not on the page: if the fixture date
    // ever slid back into an excluded month, the assertion below would be
    // unreachable and this control would go quiet along with the guard.
    expect(RENDERED_GERMAN_MONTH_NAMES)->toContain($watchedMonth);

    $html = renderAsVisitor($this, $this->meetupUrl, 'en');

    expect(renderedGermanWords($html, RENDERED_GERMAN_LITERALS))
        ->toContain($watchedMonth);
});

/*
 * Matcher-level calibration for the month names, kept alongside the page-level
 * control above because it pins down the boundary behaviour the page cannot
 * exercise: the reason April, August, September and November are absent from
 * the list is that an English page legitimately printing "August" must not
 * trip a German-word guard.
 */
it('flags a German month name in a date and spares the months English spells the same way', function () {
    $germanDate = Blade::render('<p>08. Oktober 2026</p><p>05. Januar 2027</p><p>01. März 2026</p>');

    expect(renderedGermanWords($germanDate, RENDERED_GERMAN_LITERALS))
        ->toBe(['Januar', 'März', 'Oktober']);

    $englishDate = Blade::render('<p>August 8, 2026</p><p>September 5</p><p>2026-10-08</p>');

    expect(renderedGermanWords($englishDate, RENDERED_GERMAN_LITERALS))->toBe([]);
});
