<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Blade;

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
| Known and deliberately NOT asserted: App\Support\Carbon::asDate() and its
| siblings hardcode ->locale('de'), so month and weekday names render in
| German for every visitor ("05. Januar 2027"). Separate defect, separate
| owner; putting it in the list below would leave this guard permanently red,
| and a permanently red guard gets switched off.
*/

/**
 * The German literals that were found in English screenshots on 2026-09-03.
 *
 * @var list<string>
 */
const RENDERED_GERMAN_LITERALS = ['Uhr', 'Webseite'];

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
        'start' => now()->addDays(7)->setTime(19, 30),
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
        'from' => now()->addDays(7)->setTime(18, 0),
        'to' => now()->addDays(7)->setTime(20, 0),
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
