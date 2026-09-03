<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use App\Support\Carbon as PortalCarbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/*
|--------------------------------------------------------------------------
| Dates are ISO 8601 in every locale, and in the visitor's own timezone
|--------------------------------------------------------------------------
|
| Issue #48 (external usability report): App\Support\Carbon hardcoded
| ->locale('de') in all four formatters, and AppServiceProvider binds that
| class as the application's date implementation (Date::use()). Every date in
| a nine-locale portal therefore rendered German — an English (US) visitor was
| shown "16.09.2026 19:00 (EDT)" and "19. August 2026".
|
| The owner's decision: ISO 8601 in every locale, 24-hour time everywhere.
|
|   asDate()                  2026-10-08
|   asTime()                  19:00                      (unchanged)
|   asDateTime()              2026-10-08 19:00 (CEST)    (zone suffix kept)
|
| A fourth formatter, asDayNameAndMonthName(), was deleted rather than fixed —
| see the note above the third section for why, and why no test replaces it.
|
| Two things this file does deliberately:
|
|   1. It asserts a PATTERN, never nine hardcoded per-locale strings. Nine
|      hardcoded expectations are nine chances to write a bug down as an
|      expectation. The single exception is the contract example from the
|      issue itself, on one fixed instant in one fixed zone — that literal is
|      the contract, and it is deterministic.
|   2. It asserts on RENDERED responses as well as on the class. A unit test
|      on App\Support\Carbon stays green while a Blade file bypasses it with
|      its own ->format(), which is the second defect in this issue.
|
| Sibling file RenderedLocaleTest.php watches German *literals* on rendered
| pages and explicitly excluded the date formatters ("separate defect,
| separate owner"). This file is that separate owner.
*/

/**
 * The formatters as they stood BEFORE issue #48 was fixed — copied verbatim
 * from app/Support/Carbon.php at commit 336ccec, hardcoded ->locale('de') and
 * all.
 *
 * This class is the calibration fixture. Every guard in this file has to be
 * shown failing against the behaviour it was written to catch, and once the
 * fix is in the tree there is nothing left in the repo to fail against — the
 * old code is only in git history, which no test can reach. Keeping the old
 * shape here, and binding it through Date::use() in the negative controls at
 * the bottom, makes the proof permanent instead of a one-off run somebody has
 * to remember.
 *
 * It is a fixture, never the app: production code is not touched, not even
 * temporarily. Date::use() is re-applied by AppServiceProvider::register() on
 * every test's app boot, so the binding does not leak past the test that sets
 * it.
 */
class LegacyGermanCarbon extends CarbonImmutable
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
 * A formatter that emits perfect ISO 8601 but ignores
 * config('app.user-timezone') and always renders the application default zone.
 *
 * This is the second calibration fixture, and it is the mutation the ISO
 * pattern checks are blind to by construction: "2026-10-03 03:30 (CEST)"
 * satisfies every shape assertion in this file while being the wrong instant
 * for the visitor who reported the issue.
 */
class AppDefaultZoneCarbon extends CarbonImmutable
{
    private const APP_DEFAULT_ZONE = 'Europe/Berlin';

    public function asDate(): string
    {
        return $this->timezone(self::APP_DEFAULT_ZONE)->format('Y-m-d');
    }

    public function asTime(): string
    {
        return $this->timezone(self::APP_DEFAULT_ZONE)->format('H:i');
    }

    public function asDateTime(): string
    {
        $dt = $this->timezone(self::APP_DEFAULT_ZONE);

        return sprintf('%s (%s)', $dt->format('Y-m-d H:i'), $dt->timezoneAbbreviatedName);
    }
}

/**
 * asDate() — nothing but a numeric ISO 8601 calendar date.
 */
const ISO_DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

/**
 * asDateTime() — the ISO date, 24-hour time, and the timezone label in
 * parentheses. The label is kept on purpose (issue #48): a bare local time
 * without its zone is what made the reporter mis-plan his evening.
 */
const ISO_DATE_TIME_PATTERN = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2} \([^()]+\)$/';

/**
 * asTime() — 24 hours, no am/pm, in every locale.
 */
const ISO_TIME_PATTERN = '/^\d{2}:\d{2}$/';

/**
 * A fixed instant, so the contract literals below need no "now".
 *
 * 2026-10-08 17:00 UTC is 19:00 in Europe/Berlin and 13:00 in
 * America/Indiana/Indianapolis. Both zones are unambiguously on summer time
 * that day (CEST runs to 2026-10-25, EDT to 2026-11-01), so neither literal
 * depends on when this suite runs.
 */
const CONTRACT_INSTANT_UTC = '2026-10-08 17:00:00';

/**
 * The reporter's own timezone (issue #48).
 *
 * Chosen over a one-hour neighbour on purpose: six hours from Europe/Berlin
 * means an implementation that silently skips the conversion cannot hide
 * behind an off-by-one hour.
 */
const REPORTER_TIMEZONE = 'America/Indiana/Indianapolis';

/**
 * Every locale the portal ships, read from lang/*.json rather than from a
 * list written down here — a locale added tomorrow is covered without anyone
 * remembering this file.
 *
 * @return list<string>
 */
function portalLocales(): array
{
    $locales = array_map(
        static fn (string $path): string => basename($path, '.json'),
        glob(lang_path('*.json')) ?: []
    );

    sort($locales);

    return $locales;
}

/**
 * Full month and weekday names of $locale, in that locale's own spelling.
 *
 * Full forms only, no abbreviations: an abbreviation such as "Est" could
 * collide with a timezone label and turn this into a matcher nobody trusts.
 *
 * @return list<string>
 */
function localizedDateNames(string $locale): array
{
    $names = [];

    foreach (range(1, 12) as $month) {
        $names[] = CarbonImmutable::create(2026, $month, 1, 0, 0, 0, 'UTC')->locale($locale)->monthName;
    }

    // 2026-03-01 is a Sunday, so seven consecutive days cover every weekday.
    $week = CarbonImmutable::create(2026, 3, 1, 0, 0, 0, 'UTC');

    foreach (range(0, 6) as $offset) {
        $names[] = $week->addDays($offset)->locale($locale)->dayName;
    }

    return array_values(array_unique(array_filter($names)));
}

/**
 * Month and weekday names of EVERY portal locale, in one list.
 *
 * The union is the point. Checking a date only against the names of the
 * locale that produced it would miss precisely the reported defect: a German
 * month name on an English page.
 *
 * @return list<string>
 */
function allLocalizedDateNames(): array
{
    $names = [];

    foreach (portalLocales() as $locale) {
        $names = array_merge($names, localizedDateNames($locale));
    }

    return array_values(array_unique($names));
}

/**
 * Which of $names occur in $value, case-insensitively.
 *
 * @param  list<string>  $names
 * @return list<string>
 */
function dateNameLeaks(string $value, array $names): array
{
    return array_values(array_filter(
        $names,
        static fn (string $name): bool => $name !== '' && mb_stripos($value, $name) !== false
    ));
}

/**
 * The part of an asDateTime() string in front of the timezone label.
 *
 * The label is not localized and is asserted separately; stripping it keeps
 * the name check below free of false positives.
 */
function withoutZoneLabel(string $value): string
{
    return trim((string) preg_replace('/\s*\([^()]*\)\s*$/', '', $value));
}

/**
 * The expected ISO date of $instant in $timezone, computed WITHOUT the code
 * under test — plain PHP, so a wrong conversion in App\Support\Carbon cannot
 * reproduce itself in the expectation.
 */
function zonedIsoDate(DateTimeInterface $instant, string $timezone): string
{
    return DateTimeImmutable::createFromInterface($instant)
        ->setTimezone(new DateTimeZone($timezone))
        ->format('Y-m-d');
}

/**
 * As zonedIsoDate(), plus 24-hour time and the timezone label.
 */
function zonedIsoDateTime(DateTimeInterface $instant, string $timezone): string
{
    return DateTimeImmutable::createFromInterface($instant)
        ->setTimezone(new DateTimeZone($timezone))
        ->format('Y-m-d H:i (T)');
}

/**
 * The shape a date had BEFORE this change: "08. Oktober 2026".
 */
function germanLongDate(DateTimeInterface $instant, string $timezone): string
{
    $local = CarbonImmutable::instance(
        DateTimeImmutable::createFromInterface($instant)
    )->timezone($timezone)->locale('de');

    return $local->format('d').'. '.$local->monthName.' '.$local->format('Y');
}

/**
 * expect($haystack)->toContain($needle, 'why it matters') does NOT attach a
 * message: Pest's toContain() is variadic, so the second argument becomes a
 * SECOND needle and the assertion quietly demands the message text be in the
 * haystack as well. Measured while writing this file — four assertions failed
 * with "To contain: The event page did not carry …" on pages that carried the
 * date perfectly well. These two helpers keep the diagnostic message and the
 * needle apart.
 */
function expectHtmlToContain(string $haystack, string $needle, string $message): void
{
    expect(str_contains($haystack, $needle))->toBeTrue($message);
}

function expectHtmlNotToContain(string $haystack, string $needle, string $message): void
{
    expect(str_contains($haystack, $needle))->toBeFalse($message);
}

/**
 * Requests $url as a visitor of $locale and returns the body.
 *
 * Same mechanism as RenderedLocaleTest: the locale arrives through
 * Accept-Language, which is the path DomainMiddleware takes for a first-time
 * visitor and the path the reporter came in on. Tests\TestCase blanks
 * Symfony's synthetic Accept-Language default, so this header is the only
 * thing speaking. The getLocale() check is a positive control — without it a
 * request that quietly stayed German would satisfy an "is ISO" assertion for
 * the wrong reason.
 */
function renderForVisitor(object $case, string $url, string $locale): string
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

    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Bitcoin Meetup Testhausen',
        'slug' => 'bitcoin-meetup-testhausen',
        'intro' => 'A regular gathering for bitcoiners.',
        'visible_on_map' => true,
    ]);

    /*
     * 01:30 UTC, a month out. Two properties are load-bearing:
     *
     *   - it is in the future, so meetups.landingpage lists it at all
     *     (the component filters on `start >= now()`);
     *   - at 01:30 UTC the local CALENDAR DAY differs between Europe/Berlin
     *     (03:30, same day) and the reporter's zone (21:30, day before). A
     *     rendered date that ignores the visitor's timezone therefore fails
     *     on the day, not only on the hour.
     *
     * The instant is fixed relative to now() rather than to a calendar date,
     * so the test does not rot; every expectation is computed from it with
     * plain PHP.
     */
    $this->eventStart = CarbonImmutable::now('UTC')->addDays(30)->setTime(1, 30);

    $this->event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => $this->eventStart,
        'location' => 'Main Street 1',
        'description' => 'An evening about bitcoin.',
        'recurrence_type' => null,
    ]);

    $this->meetupUrl = route('meetups.landingpage', ['country' => 'de', 'meetup' => $this->meetup->slug]);
    $this->eventUrl = route('meetups.landingpage-event', [
        'country' => 'de',
        'meetup' => $this->meetup->slug,
        'event' => $this->event->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| 1. The formatters emit ISO in every locale
|--------------------------------------------------------------------------
*/

it('formats a date as ISO 8601 in every portal locale', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $locales = portalLocales();

    // The floor belongs in an assertion, not in a comment: a glob that
    // silently returns two files would make the loop below pass for free.
    expect(count($locales))->toBeGreaterThanOrEqual(9, 'Fewer locale files than the nine the portal ships: '.implode(', ', $locales));
    expect($locales)->toContain('de')->toContain('en');

    $offenders = [];

    foreach ($locales as $locale) {
        app()->setLocale($locale);

        $formatted = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asDate();

        if (preg_match(ISO_DATE_PATTERN, $formatted) !== 1) {
            $offenders[$locale] = $formatted;
        }
    }

    expect($offenders)->toBe([], 'asDate() did not return YYYY-MM-DD in these locales.');
});

it('formats a date and time as ISO 8601 with a zone label in every portal locale', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $offenders = [];

    foreach (portalLocales() as $locale) {
        app()->setLocale($locale);

        $formatted = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asDateTime();

        if (preg_match(ISO_DATE_TIME_PATTERN, $formatted) !== 1) {
            $offenders[$locale] = $formatted;
        }
    }

    expect($offenders)->toBe([], 'asDateTime() did not return "YYYY-MM-DD HH:MM (ZONE)" in these locales.');
});

it('keeps time at 24 hours in every portal locale', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $offenders = [];

    foreach (portalLocales() as $locale) {
        app()->setLocale($locale);

        $formatted = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asTime();

        if (preg_match(ISO_TIME_PATTERN, $formatted) !== 1 || $formatted !== '19:00') {
            $offenders[$locale] = $formatted;
        }
    }

    expect($offenders)->toBe([], 'asTime() is no longer 24-hour "19:00" in these locales.');
});

/*
|--------------------------------------------------------------------------
| 2. No month or day name leaks into a numeric date, in any locale
|--------------------------------------------------------------------------
|
| This is the actual regression class. It runs in both directions: a German
| month name must not reach an English visitor (what was reported), and a
| German visitor must not get "Oktober" back from asDate() either — ISO means
| ISO for everyone.
*/

it('leaks no month or day name of any portal locale into a numeric date', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $names = allLocalizedDateNames();

    // Positive control on the name list itself. If Carbon ever stops
    // resolving these locales, $names would be empty and every check below
    // would pass without looking at anything.
    expect($names)->toContain('Oktober')->toContain('October')->toContain('Donnerstag');

    $offenders = [];

    foreach (portalLocales() as $locale) {
        app()->setLocale($locale);

        $instant = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC');

        $date = $instant->asDate();
        $dateTime = withoutZoneLabel($instant->asDateTime());

        foreach (['asDate' => $date, 'asDateTime' => $dateTime] as $method => $value) {
            $leaks = dateNameLeaks($value, $names);

            if ($leaks !== []) {
                $offenders["{$locale}/{$method}"] = $value.' → '.implode(', ', $leaks);
            }

            // Belt to the braces: the numeric part of a date carries no
            // letters at all, so even a name Carbon does not know is caught.
            if (preg_match('/\p{L}/u', $value) === 1 && ! isset($offenders["{$locale}/{$method}"])) {
                $offenders["{$locale}/{$method}"] = $value.' → contains letters';
            }
        }
    }

    expect($offenders)->toBe([], 'Month or day names reached a numeric date.');
});

it('emits the exact shapes the issue #48 contract names', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);
    app()->setLocale('en');

    $instant = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC');

    // One fixed instant, one fixed zone, both on summer time that day. These
    // three literals ARE the contract from the issue, not a per-locale guess.
    expect($instant->asDate())->toBe('2026-10-08');
    expect($instant->asTime())->toBe('19:00');
    expect($instant->asDateTime())->toBe('2026-10-08 19:00 (CEST)');
});

/*
|--------------------------------------------------------------------------
| 3. There is no fourth formatter
|--------------------------------------------------------------------------
|
| asDayNameAndMonthName() was the one formatter that kept day and month names,
| and the contract handed to this file described it as following the active
| locale. It does not exist any more, by a decision confirmed on 2026-09-03:
| a repo-wide grep found exactly one hit, its own definition — no blade, no
| controller, no test, no dynamic call. It was also bilingual by construction,
| interpolating a German dayName and monthName into the literal English frame
| '%s, %s. week of %s [%s]', which could not be repaired without a new
| lang/*.json key. Deleting dead code beat translating it for zero callers.
|
| Nothing is asserted here on purpose. This note exists so the gap between the
| four-method contract and the three methods below reads as a decision rather
| than as an omission.
*/

/*
|--------------------------------------------------------------------------
| 4. Rendered, not just unit-level
|--------------------------------------------------------------------------
|
| A unit test on App\Support\Carbon passes even when a view bypasses it with
| its own ->format(). Both assertions below run against a real response.
*/

it('renders an ISO date on the meetup landing page', function (string $locale) {
    $html = renderForVisitor($this, $this->meetupUrl, $locale);

    $expected = zonedIsoDate($this->eventStart, 'Europe/Berlin');

    // Positive: the page really did render the date region.
    expectHtmlToContain($html, $expected, "The meetup landing page did not carry the ISO date {$expected} for a '{$locale}' visitor.");

    // Negative: the shape it carried before this change is gone. Both forms,
    // because the pre-change asDate() produced the long one and asDateTime()
    // the dotted one.
    expect($html)->not->toContain(germanLongDate($this->eventStart, 'Europe/Berlin'));
    expect($html)->not->toContain(
        DateTimeImmutable::createFromInterface($this->eventStart)
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y')
    );
})->with(['en', 'de']);

it('renders an ISO date and time on the meetup event page', function (string $locale) {
    $html = renderForVisitor($this, $this->eventUrl, $locale);

    $expectedDateTime = zonedIsoDateTime($this->eventStart, 'Europe/Berlin');
    $expectedDate = zonedIsoDate($this->eventStart, 'Europe/Berlin');

    expectHtmlToContain($html, $expectedDateTime, "The event page did not carry \"{$expectedDateTime}\" for a '{$locale}' visitor.");
    expectHtmlToContain($html, $expectedDate, "The event page did not carry the ISO date {$expectedDate} for a '{$locale}' visitor.");

    expect($html)->not->toContain(germanLongDate($this->eventStart, 'Europe/Berlin'));
    expect($html)->not->toContain(
        DateTimeImmutable::createFromInterface($this->eventStart)
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y')
    );
})->with(['en', 'de']);

/*
|--------------------------------------------------------------------------
| 5. The visitor's own timezone is visible, and named
|--------------------------------------------------------------------------
*/

it('converts to the configured user timezone and names it', function () {
    app()->setLocale('en');

    config(['app.user-timezone' => 'Europe/Berlin']);
    $berlin = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asDateTime();

    config(['app.user-timezone' => REPORTER_TIMEZONE]);
    $indianapolis = PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asDateTime();

    expect($berlin)->toBe('2026-10-08 19:00 (CEST)');
    expect($indianapolis)->toBe('2026-10-08 13:00 (EDT)');

    // The two must not be the same string. Without this, an implementation
    // that dropped the conversion entirely could still satisfy one of the
    // literals above if the fixture zones ever converged.
    expect($indianapolis)->not->toBe($berlin);

    config(['app.user-timezone' => REPORTER_TIMEZONE]);
    expect(PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asTime())->toBe('13:00');
});

it('renders the event page in the timezone each visitor configured, not the app default', function () {
    /*
     * One stored instant, one page, two visitors who differ in nothing but
     * their users.timezone column. That is what makes this a measurement of
     * the conversion rather than of a constant: a page that ignored the column
     * would hand both of them the same string, and the two visitors' own
     * strings are asserted absent from each other's response.
     *
     * config('app.user-timezone') is the authoritative source, written by
     * SetTimezone from users.timezone. PHP's own default zone stays UTC
     * throughout — date_default_timezone_set() ran at bootstrap, long before
     * any middleware — so ->timezone(config('app.user-timezone')) is the only
     * thing that converts anything.
     *
     * Measured after a real request with an Indianapolis user, rather than
     * assumed: date_default_timezone_get() = 'UTC', while config('app.timezone')
     * = 'America/Indiana/Indianapolis'. SetTimezone.php:20 writes that key too;
     * it is simply not what the formatters read, and not what keeps PHP on UTC.
     *
     * Auckland sits on the other side of the app default from Indianapolis, so
     * "the visitor's date is one day behind" cannot be satisfied by a fixed
     * offset in the wrong direction.
     */
    $visitors = [
        // At 01:30 UTC the reporter's zone (UTC-4/-5) is on the PREVIOUS
        // calendar day, so his date fails on the day and not merely on the
        // hour. Auckland (UTC+12/+13) is on the same day as Berlin; it is here
        // for the opposite direction of the offset, and its date is therefore
        // asserted to match the app default's while its time does not.
        REPORTER_TIMEZONE => ['user' => null, 'sameCalendarDayAsAppDefault' => false],
        'Pacific/Auckland' => ['user' => null, 'sameCalendarDayAsAppDefault' => true],
    ];

    $appDefault = zonedIsoDateTime($this->eventStart, 'Europe/Berlin');
    $rendered = [];

    foreach ($visitors as $timezone => $expectation) {
        $visitor = User::factory()->create(['timezone' => $timezone]);
        $expected = zonedIsoDateTime($this->eventStart, $timezone);

        expect($expected)->not->toBe($appDefault);
        expect(zonedIsoDate($this->eventStart, $timezone) === zonedIsoDate($this->eventStart, 'Europe/Berlin'))
            ->toBe($expectation['sameCalendarDayAsAppDefault'], "The fixture instant no longer places {$timezone} where beforeEach() says it does.");

        $html = (string) $this->actingAs($visitor)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get($this->eventUrl)
            ->assertSuccessful()
            ->getContent();

        expectHtmlToContain($html, $expected, "The event page did not show the {$timezone} visitor their own time \"{$expected}\".");
        expectHtmlNotToContain($html, $appDefault, "The event page showed the app default zone (\"{$appDefault}\") to a visitor who configured {$timezone}.");

        $rendered[$timezone] = $html;
    }

    // Cross-check: neither visitor was served the other one's time.
    expectHtmlNotToContain(
        $rendered[REPORTER_TIMEZONE],
        zonedIsoDateTime($this->eventStart, 'Pacific/Auckland'),
        'The Indianapolis visitor was served the Auckland time — the output does not track users.timezone.'
    );
    expectHtmlNotToContain(
        $rendered['Pacific/Auckland'],
        zonedIsoDateTime($this->eventStart, REPORTER_TIMEZONE),
        'The Auckland visitor was served the Indianapolis time — the output does not track users.timezone.'
    );
});

it('shows the created and updated timestamps of the admin form in the visitor timezone, with the zone named', function () {
    $editor = User::factory()->create(['timezone' => REPORTER_TIMEZONE]);

    // promoteLeader(), not created_by: `created_by` is not fillable on Meetup,
    // so an update() of it no-ops and the page answers 403. MeetupPolicy@update
    // accepts a leader on the meetup_user pivot.
    $this->meetup->promoteLeader($editor);

    $this->actingAs($editor);

    // A real request, not Livewire::test(): the component test runs without
    // middleware, so SetTimezone would never fire and app.user-timezone would
    // stay on the config default. That would prove nothing about a visitor.
    $response = $this->get(route('meetups.edit', ['country' => 'de', 'meetup' => $this->meetup->id]));
    $response->assertSuccessful();

    $html = (string) $response->getContent();

    $createdAt = $this->meetup->fresh()->created_at;
    $expected = zonedIsoDateTime($createdAt, REPORTER_TIMEZONE);
    $appDefault = zonedIsoDateTime($createdAt, 'Europe/Berlin');

    // Positive control: a fixture created "just now" would render the same
    // string in both zones if the two happened to agree on minute and label.
    expect($expected)->not->toBe($appDefault);

    expectHtmlToContain($html, $expected, "The edit form did not show created_at as \"{$expected}\".");
    expectHtmlNotToContain($html, $appDefault, "The edit form showed created_at in the app default zone (\"{$appDefault}\").");
});

/*
|--------------------------------------------------------------------------
| 6. Calibration — the assertions above must be able to fail
|--------------------------------------------------------------------------
|
| Written against fixtures rather than against the app, so it survives the
| fix. Once App\Support\Carbon emits ISO, every other test in this file goes
| green and nothing left in the repo would show that they ever caught
| anything. This one keeps the old shapes on file and proves the matchers
| still reject them.
|
| The strings are the ones issue #48 reported verbatim.
*/

it('rejects the German shapes this suite was written against', function () {
    $names = allLocalizedDateNames();

    // "19. August 2026" — the pre-change asDate().
    expect(preg_match(ISO_DATE_PATTERN, '19. August 2026'))->toBe(0);
    expect(dateNameLeaks('19. August 2026', $names))->toContain('August');

    // "08. Oktober 2026" — same shape, a month whose German spelling differs
    // from the English one.
    expect(preg_match(ISO_DATE_PATTERN, '08. Oktober 2026'))->toBe(0);
    expect(dateNameLeaks('08. Oktober 2026', $names))->toContain('Oktober');

    // "16.09.2026 19:00 (EDT)" — the pre-change asDateTime(), quoted from the
    // report. Numeric, so the name check is blind to it; the pattern is not.
    expect(preg_match(ISO_DATE_TIME_PATTERN, '16.09.2026 19:00 (EDT)'))->toBe(0);
    expect(dateNameLeaks(withoutZoneLabel('16.09.2026 19:00 (EDT)'), $names))->toBe([]);

    // A correct ISO date and time must pass all of it, or the matchers above
    // are simply rejecting everything.
    expect(preg_match(ISO_DATE_PATTERN, '2026-10-08'))->toBe(1);
    expect(preg_match(ISO_DATE_TIME_PATTERN, '2026-10-08 19:00 (CEST)'))->toBe(1);
    expect(preg_match(ISO_TIME_PATTERN, '19:00'))->toBe(1);
    expect(dateNameLeaks(withoutZoneLabel('2026-10-08 19:00 (CEST)'), $names))->toBe([]);

    // Both a day and a month name in one string are reported, in order, so a
    // failure names every leak rather than the first one.
    expect(dateNameLeaks('Donnerstag, 08. Oktober 2026', ['Donnerstag', 'Oktober']))
        ->toBe(['Donnerstag', 'Oktober']);

    // 12-hour time must not pass for 24-hour time.
    expect(preg_match(ISO_TIME_PATTERN, '7:00 PM'))->toBe(0);
});

it('fails its own locale checks against the pre-issue-48 formatter', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $names = allLocalizedDateNames();
    $survivors = [];

    foreach (portalLocales() as $locale) {
        app()->setLocale($locale);

        $legacy = LegacyGermanCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC');

        // Every locale must fail, and fail for the reported reason: the shape
        // is not ISO, and a German month name sits inside a numeric date.
        if (preg_match(ISO_DATE_PATTERN, $legacy->asDate()) !== 0) {
            $survivors["{$locale}/asDate/pattern"] = $legacy->asDate();
        }

        if (dateNameLeaks($legacy->asDate(), $names) === []) {
            $survivors["{$locale}/asDate/names"] = $legacy->asDate();
        }

        if (preg_match(ISO_DATE_TIME_PATTERN, $legacy->asDateTime()) !== 0) {
            $survivors["{$locale}/asDateTime/pattern"] = $legacy->asDateTime();
        }

        if (dateNameLeaks(withoutZoneLabel($legacy->asDateTime()), $names) !== []) {
            // The old asDateTime() was numeric ("16.09.2026 19:00 (EDT)"), so
            // the NAME check is blind to it by design and only the pattern
            // catches it. Recorded as an expectation, not left to chance: if a
            // name ever did appear here, the two checks would no longer be
            // testing what this file says they test.
            $survivors["{$locale}/asDateTime/names"] = $legacy->asDateTime();
        }
    }

    expect($survivors)->toBe([], 'These checks stayed silent on the old German formatter — they cannot be catching the regression they were written for.');

    // asTime() is the one formatter issue #48 leaves unchanged. Stated here so
    // the absence of a legacy check above is a decision on the record and not
    // an omission.
    app()->setLocale('en');
    expect(LegacyGermanCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asTime())
        ->toBe(PortalCarbon::parse(CONTRACT_INSTANT_UTC, 'UTC')->asTime());
});

it('fails its own page checks against the pre-issue-48 formatter', function (string $url) {
    /*
     * The rendered guards are the ones that could pass for the wrong reason —
     * a page that never printed a date satisfies "no German date" for free.
     * Binding the old formatter puts the German shape back on the real page
     * and shows both directions of the assertion moving.
     */
    Date::use(LegacyGermanCarbon::class);

    $html = renderForVisitor($this, $this->{$url}, 'en');

    $isoDate = zonedIsoDate($this->eventStart, 'Europe/Berlin');
    $germanLong = germanLongDate($this->eventStart, 'Europe/Berlin');

    expectHtmlNotToContain($html, $isoDate, "The old formatter still rendered the ISO date {$isoDate} — the positive assertion in the real test is not what makes it pass.");
    expectHtmlToContain($html, $germanLong, "The old formatter did not put \"{$germanLong}\" on the page — the negative assertion in the real test watches for a shape this page never had.");

    /*
     * The same run is the proof for the sibling guard: with ->locale('de')
     * back, a German MONTH NAME reaches the page as a whole word, which is
     * what RenderedLocaleTest's RENDERED_GERMAN_MONTH_NAMES entries watch for.
     * That list carries only the months English spells differently, so the
     * name asserted here is not always one of them — but a month name on the
     * page is the condition all of them share.
     */
    $germanMonth = CarbonImmutable::instance(DateTimeImmutable::createFromInterface($this->eventStart))
        ->timezone('Europe/Berlin')->locale('de')->monthName;

    expectHtmlToContain($html, $germanMonth, "A German month name did not reach the page under the old formatter, so the month-name guard in RenderedLocaleTest watches for something this page cannot produce ({$germanMonth}).");
})->with([
    'meetup landing page' => 'meetupUrl',
    'meetup event page' => 'eventUrl',
]);

it('fails its own timezone checks against a formatter that skips the conversion', function () {
    /*
     * The ISO shape assertions cannot see this mutation — AppDefaultZoneCarbon
     * emits flawless ISO. Only the two timezone tests can, so they are the ones
     * that have to be shown falling.
     */
    $reporter = User::factory()->create(['timezone' => REPORTER_TIMEZONE]);
    $this->meetup->promoteLeader($reporter);
    $this->actingAs($reporter);

    Date::use(AppDefaultZoneCarbon::class);

    $reporterTime = zonedIsoDateTime($this->eventStart, REPORTER_TIMEZONE);
    $appDefault = zonedIsoDateTime($this->eventStart, 'Europe/Berlin');

    $eventHtml = (string) $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
        ->get($this->eventUrl)->assertSuccessful()->getContent();

    expectHtmlNotToContain($eventHtml, $reporterTime, "A formatter that ignores the visitor timezone still produced \"{$reporterTime}\" — the event-page timezone test cannot be measuring the conversion.");
    expectHtmlToContain($eventHtml, $appDefault, "The page did not fall back to the app default zone (\"{$appDefault}\"), so the negative half of the event-page timezone test watches for something unreachable.");

    // Same mutation, the admin form — a second rendering path with its own
    // assertion, so it needs its own proof.
    $createdAt = $this->meetup->fresh()->created_at;

    $editHtml = (string) $this->get(route('meetups.edit', ['country' => 'de', 'meetup' => $this->meetup->id]))
        ->assertSuccessful()->getContent();

    expectHtmlNotToContain($editHtml, zonedIsoDateTime($createdAt, REPORTER_TIMEZONE), 'The edit form still showed created_at in the visitor timezone under a formatter that ignores it.');
    expectHtmlToContain($editHtml, zonedIsoDateTime($createdAt, 'Europe/Berlin'), 'The edit form did not show created_at in the app default zone under a formatter that ignores the visitor timezone.');
});
