<?php

use App\Enums\RecurrenceType;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| What the date pickers STORE, and what the series preview DISPLAYS
|--------------------------------------------------------------------------
|
| Two behaviours from the issue #48 fix, both proven by hand and neither
| guarded until now.
|
| The owner's acceptance condition is the first one, in his words:
| "hauptsache der datepicker speichert richtig in die DB (welche UTC ist)".
| The rule he settled on afterwards draws the line this file is organised
| around: a picker is an INPUT CONTROL, ISO applies to DATA DISPLAY. So
| nothing here asserts what a picker shows — only what it stores, and what
| the preview renders. That is also why the brief experiment of pinning the
| Flux pickers to lt-LT (to force an ISO display) could be reverted without
| loss: the stored instant never depended on it, and the test below pins that
| independence so the question stays permanently harmless.
|
| Placed here rather than in tests/Feature/Meetups or tests/Feature/Courses
| because it covers BOTH create-edit-events components, which live in those
| two different directories; and because storage and preview belong in one
| file — they are the two halves of the same control, and the input/display
| rule above is only legible when they sit side by side.
|
| Every storage assertion reads the RAW column through the query builder, not
| a re-parsed Carbon. A model attribute comes back through the same cast that
| wrote it, so a conversion that is wrong in both directions would agree with
| itself; the column does not.
*/

/**
 * The organiser whose report started issue #48.
 *
 * Indianapolis is EDT (UTC-4) in September, so 21:30 local moves BOTH the hour
 * and the calendar date when it becomes UTC. That is the property to keep when
 * touching these fixtures — it is what stops an off-by-one from hiding.
 */
const ORGANISER_TIMEZONE = 'America/Indiana/Indianapolis';

/**
 * The owner's own measurement: entered 2026-09-14 / 21:30, stored
 * 2026-09-15 01:30:00.
 */
const ENTERED_DATE = '2026-09-14';

const ENTERED_TIME = '21:30';

const EXPECTED_UTC = '2026-09-15 01:30:00';

/**
 * The nine locales the portal ships, plus lt-LT — the locale the pickers were
 * briefly pinned to and which was reverted. It is in the list precisely
 * because no picker names it any more (it remains selectable via
 * config/lang-country.php): the point of the test is that the display locale
 * cannot reach the stored value at all.
 *
 * @return list<string>
 */
function displayLocales(): array
{
    $locales = array_map(
        static fn (string $path): string => basename($path, '.json'),
        glob(langDirectory().'/*.json') ?: []
    );

    sort($locales);

    return [...$locales, 'lt'];
}

/**
 * The lang directory, reached without the framework.
 *
 * lang_path() cannot be used here: this function feeds a with() dataset, and
 * Pest resolves datasets while collecting the suite — before the application
 * is booted, so the helper is not available yet (measured: "DatasetMissing",
 * because the glob silently produced nothing). The path is checked against
 * lang_path() in its own test below, so moving this file cannot make the
 * dataset quietly shrink to one entry.
 */
function langDirectory(): string
{
    return dirname(__DIR__, 4).'/lang';
}

/**
 * The raw column value, straight from the database.
 */
function rawColumn(string $table, int $id, string $column): ?string
{
    return DB::table($table)->where('id', $id)->value($column);
}

/**
 * Weekday names of every portal locale, in that locale's own spelling.
 *
 * The union, not just the active locale's: translatedFormat('l') followed the
 * app locale, so the leak this guards against appears in whichever language
 * the visitor happens to be reading.
 *
 * @return list<string>
 */
function everyLocaleDayNames(): array
{
    $names = [];

    // 2026-03-01 is a Sunday, so seven consecutive days cover every weekday.
    $week = CarbonImmutable::create(2026, 3, 1, 0, 0, 0, 'UTC');

    foreach (displayLocales() as $locale) {
        foreach (range(0, 6) as $offset) {
            $names[] = $week->addDays($offset)->locale($locale)->dayName;
        }
    }

    return array_values(array_unique(array_filter($names)));
}

/**
 * Which of $names occur in $value as whole words, case-insensitively.
 *
 * Whole words matter here for the same reason as in RenderedLocaleTest: the
 * Czech "út" or the Latvian "otrdiena" inside a longer token would otherwise
 * report a leak that is not there.
 *
 * @param  list<string>  $names
 * @return list<string>
 */
function dayNameLeaks(string $value, array $names): array
{
    return array_values(array_filter(
        $names,
        static fn (string $name): bool => preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/ui',
            $value
        ) === 1
    ));
}

/**
 * The markup around every occurrence of $needle in $html.
 *
 * The preview cards are the only place the formatted date appears, so a window
 * around the date is the preview region — and it is locale-independent, unlike
 * anchoring on the translated heading. The window has to be tight: the form
 * also renders a weekday SELECT (Montag, Dienstag, …), which is correct German
 * UI and must not be mistaken for a date leak.
 */
function markupAround(string $html, string $needle, int $radius = 160): string
{
    $windows = [];
    $offset = 0;

    while (($position = mb_strpos($html, $needle, $offset)) !== false) {
        $windows[] = mb_substr($html, max(0, $position - $radius), $radius * 2);
        $offset = $position + 1;
    }

    return implode("\n", $windows);
}

beforeEach(function () {
    $this->organiser = actingAsUser(['timezone' => ORGANISER_TIMEZONE]);
    $this->meetup = Meetup::factory()->create(['created_by' => $this->organiser->id]);
});

/*
|--------------------------------------------------------------------------
| 1. The picker stores the correct UTC instant
|--------------------------------------------------------------------------
*/

it('reaches the real lang directory without the framework', function () {
    // The dataset below is only as wide as this path is right. If the file
    // moves, dirname(__DIR__, 4) points somewhere else, the glob returns
    // nothing, and the per-locale test would silently run for 'lt' alone.
    expect(realpath(langDirectory()))->toBe(realpath(lang_path()));
    expect(displayLocales())->toContain('de')->toContain('en')->toContain('lt');
    expect(count(displayLocales()))->toBeGreaterThanOrEqual(10);
});

it('stores the organiser wall-clock entry as the correct UTC instant', function () {
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('startDate', ENTERED_DATE)
        ->set('startTime', ENTERED_TIME)
        ->set('location', 'Main Street 1')
        ->set('description', 'An evening about bitcoin.')
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->sole();
    $stored = rawColumn('meetup_events', $event->id, 'start');

    expect($stored)->toBe(EXPECTED_UTC);

    /*
     * The three ways this has been got wrong, named rather than merely
     * excluded by the line above. Each is a value the column would carry
     * under a plausible implementation:
     *
     *   - the wall clock written through as if it were already UTC;
     *   - the offset applied in the wrong direction;
     *   - the date left alone because only the hour was converted.
     */
    expect($stored)->not->toBe('2026-09-14 21:30:00');
    expect($stored)->not->toBe('2026-09-14 17:30:00');
    expect(substr($stored, 0, 10))->not->toBe(ENTERED_DATE);
});

it('stores the same UTC instant whatever locale the form is displayed in', function () {
    $storedPerLocale = [];

    foreach (displayLocales() as $locale) {
        app()->setLocale($locale);

        MeetupEvent::query()->delete();

        Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
            ->set('startDate', ENTERED_DATE)
            ->set('startTime', ENTERED_TIME)
            ->set('location', 'Main Street 1')
            ->set('description', 'An evening about bitcoin.')
            ->call('save')
            ->assertHasNoErrors();

        $event = MeetupEvent::query()->sole();
        $storedPerLocale[$locale] = rawColumn('meetup_events', $event->id, 'start');
    }

    // The floor is asserted, not assumed: an empty glob would make the
    // "identical everywhere" claim below true over nothing.
    expect(count($storedPerLocale))->toBeGreaterThanOrEqual(10);
    expect(array_keys($storedPerLocale))->toContain('de')->toContain('en')->toContain('lt');

    expect(array_unique(array_values($storedPerLocale)))->toBe([EXPECTED_UTC]);
});

it('stores both ends of a course event as UTC', function () {
    $course = Course::factory()->create(['created_by' => $this->organiser->id]);
    $city = City::factory()->create();

    Livewire::test('courses.create-edit-events', ['course' => $course])
        ->set('fromDate', ENTERED_DATE)
        ->set('fromTime', ENTERED_TIME)
        ->set('toDate', '2026-09-14')
        ->set('toTime', '23:00')
        ->set('city_id', $city->id)
        ->set('location', 'Main Street 1')
        ->set('link', 'https://example.com/course-event')
        ->call('save')
        ->assertHasNoErrors();

    $event = CourseEvent::query()->sole();

    expect(rawColumn('course_events', $event->id, 'from'))->toBe(EXPECTED_UTC);
    expect(rawColumn('course_events', $event->id, 'to'))->toBe('2026-09-15 03:00:00');
});

/*
|--------------------------------------------------------------------------
| 2. A series keeps the organiser's wall clock across a DST change
|--------------------------------------------------------------------------
|
| createEventSeries() expands the dates itself and converts afterwards, which
| is a different code path from the single event above and was never measured.
|
| The property is wall-clock stability in the organiser's zone, NOT equal
| spacing in UTC. US zones leave DST on 2026-11-01, so a weekly 19:00 series
| that crosses it must store 23:00 UTC before the switch and 00:00 UTC after
| it. An implementation that advances the UTC instant by seven days produces
| 23:00 UTC throughout — 18:00 local, an hour early, silently, for every
| occurrence after the switch.
*/

it('keeps every occurrence of a series on the organiser wall clock across the DST change', function () {
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-10-28')
        ->set('startTime', '19:00')
        ->set('endDate', '2026-11-11')
        ->set('recurrenceType', RecurrenceType::Weekly)
        ->set('location', 'Main Street 1')
        ->set('description', 'A weekly evening about bitcoin.')
        ->call('save')
        ->assertHasNoErrors();

    $stored = MeetupEvent::query()
        ->orderBy('start')
        ->pluck('id')
        ->map(fn (int $id): string => rawColumn('meetup_events', $id, 'start'))
        ->all();

    // Three Wednesdays: 28 October (EDT), 4 and 11 November (EST).
    expect($stored)->toBe([
        '2026-10-28 23:00:00',
        '2026-11-05 00:00:00',
        '2026-11-12 00:00:00',
    ]);

    // The same claim expressed as the property, so a future fixture change
    // cannot quietly turn the list above into three numbers nobody checks.
    $wallClocks = array_map(
        static fn (string $utc): string => CarbonImmutable::createFromFormat('Y-m-d H:i:s', $utc, 'UTC')
            ->setTimezone(ORGANISER_TIMEZONE)
            ->format('H:i'),
        $stored
    );

    expect($wallClocks)->toBe(['19:00', '19:00', '19:00']);

    /*
     * The discriminator. A naive implementation that adds seven days to the
     * UTC instant satisfies "three occurrences, a week apart" perfectly and
     * fails only here: correct output is deliberately NOT equally spaced,
     * because one of the gaps swallows the extra hour.
     */
    $gaps = [
        CarbonImmutable::parse($stored[0], 'UTC')->diffInHours(CarbonImmutable::parse($stored[1], 'UTC')),
        CarbonImmutable::parse($stored[1], 'UTC')->diffInHours(CarbonImmutable::parse($stored[2], 'UTC')),
    ];

    expect($gaps)->toBe([169.0, 168.0], 'Equal UTC spacing across a DST change means the wall clock moved.');
});

/*
|--------------------------------------------------------------------------
| 3. Reopening the edit form does not move the instant
|--------------------------------------------------------------------------
|
| mount() reads start->setTimezone($timezone) back into the pickers, save()
| converts the other way. The two are only symmetrical if they agree, and
| nobody had run the round trip.
*/

it('does not move a stored instant when the edit form is reopened and saved unchanged', function () {
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('startDate', ENTERED_DATE)
        ->set('startTime', ENTERED_TIME)
        ->set('location', 'Main Street 1')
        ->set('description', 'An evening about bitcoin.')
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->sole();

    expect(rawColumn('meetup_events', $event->id, 'start'))->toBe(EXPECTED_UTC);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup, 'event' => $event])
        // The pickers must come back carrying the organiser's own wall clock,
        // not UTC — otherwise the round trip below would be trivially stable
        // while the form showed the wrong time.
        ->assertSet('startDate', ENTERED_DATE)
        ->assertSet('startTime', ENTERED_TIME)
        ->call('save')
        ->assertHasNoErrors();

    expect(rawColumn('meetup_events', $event->id, 'start'))->toBe(EXPECTED_UTC);
});

/*
|--------------------------------------------------------------------------
| 4. The series preview is data display, so it is ISO and carries no day name
|--------------------------------------------------------------------------
|
| create-edit-events.blade.php:66 used translatedFormat('l, d.m.Y') and put
| "Monday, 05.10.2026" on the reporter's own page — the thirteenth display
| site, and the one the original sweep's grep patterns could not match. The
| day name is what made it a locale leak rather than a mere format
| inconsistency, so that is the half asserted per locale.
*/

it('renders the series preview as ISO with no day name, in every portal locale', function (string $locale) {
    app()->setLocale($locale);

    $html = Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-10-05')
        ->set('startTime', '19:00')
        ->set('endDate', '2026-10-19')
        ->set('recurrenceType', RecurrenceType::Weekly)
        ->html();

    $expectedDates = ['2026-10-05', '2026-10-12', '2026-10-19'];

    foreach ($expectedDates as $date) {
        // Positive control first: without it, "no day name near the date"
        // would be satisfied by a preview that never rendered.
        expect(str_contains($html, $date))->toBeTrue("The preview did not render {$date} under locale '{$locale}'.");

        expect(dayNameLeaks(markupAround($html, $date), everyLocaleDayNames()))
            ->toBe([], "A weekday name is rendered next to {$date} under locale '{$locale}'.");
    }

    // The other half of the old shape. Asserted over the WHOLE form, not just
    // the preview: d.m.Y has no legitimate place anywhere on this page.
    expect(preg_match('/\d{2}\.\d{2}\.\d{4}/', $html))
        ->toBe(0, "A d.m.Y date is rendered somewhere in the event form under locale '{$locale}'.");
})->with(displayLocales());

/*
|--------------------------------------------------------------------------
| 5. Calibration — the day-name matcher must be able to fail
|--------------------------------------------------------------------------
|
| The preview cannot produce the old shape any more, so nothing on a rendered
| page can exercise the matcher. It is calibrated on the string the reporter
| actually saw instead, which keeps the proof in the repo after the fix.
*/

it('flags the pre-issue-48 preview shape and spares the corrected one', function () {
    $names = everyLocaleDayNames();

    // Positive control on the name list itself: empty, and everything below
    // would pass without looking at anything.
    expect($names)->toContain('Monday')->toContain('Montag')->toContain('pirmadienis');

    // What translatedFormat('l, d.m.Y') rendered, in the two locales the
    // reporter and the owner were reading.
    expect(dayNameLeaks('Monday, 05.10.2026', $names))->toBe(['Monday']);
    expect(dayNameLeaks('Montag, 05.10.2026', $names))->toBe(['Montag']);
    expect(preg_match('/\d{2}\.\d{2}\.\d{4}/', 'Monday, 05.10.2026'))->toBe(1);

    // What it renders now.
    expect(dayNameLeaks('2026-10-05', $names))->toBe([]);
    expect(preg_match('/\d{2}\.\d{2}\.\d{4}/', '2026-10-05'))->toBe(0);

    /*
     * Why the assertion above looks at a window around the date instead of at
     * the whole page. This one line of the very same form scores TWO hits: the
     * visible "Montag" of the weekday select, which is correct German UI, and
     * the machine value="monday" in the attribute, which is not text at all.
     * Neither is a date leak, and a whole-page day-name check would report
     * both on every render — the guard would be red permanently and switched
     * off within a day. Measured while writing this test; the second hit was
     * not anticipated.
     */
    $weekdaySelect = '<option value="monday">Montag</option>';

    expect(dayNameLeaks($weekdaySelect, $names))->toBe(['Montag', 'Monday']);
    expect(markupAround($weekdaySelect, '2026-10-05'))->toBe('');
});
