<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

/*
 * Issue #85: MeetupEventResource passed `start` / `end` through as raw Carbon objects,
 * so Laravel's default serialisation decided the format — `2026-09-16T17:00:00.000000Z`.
 * That is a THIRD spelling of one instant next to the `Y-m-d H:i` and the
 * `+00:00` form the list endpoints emit since #71.
 *
 * The fix is ADDITIVE, exactly as in #71: `start_iso` / `end_iso` are new and carry the
 * `+00:00` form; the Carbon-serialised fields stay byte for byte until the consumers of
 * the authenticated endpoints and of the MCP tools have moved over. Half of this file
 * therefore asserts that the old fields did NOT move.
 *
 * Every assertion is on the literal emitted string, and the deprecated literals below
 * were measured against `master` before the change rather than copied from the issue.
 * `assertJsonStructure()` or `toHaveKey()` would stay green through exactly the silent
 * format change this issue is about.
 */

beforeEach(function () {
    $this->owner = User::factory()->create();
    Sanctum::actingAs($this->owner);

    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    // The creator becomes leader of the meetup through the model's booted hook, which
    // is what makes the event show up in `mine` / `mineShow`.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'created_by' => $this->owner->id,
    ]);
});

function meetupEventForResource(array $attributes = []): MeetupEvent
{
    return MeetupEvent::factory()->create([
        'meetup_id' => test()->meetup->id,
        'created_by' => test()->owner->id,
        'start' => '2026-09-16 17:00:00',
        'end' => '2026-09-16 20:30:00',
        ...$attributes,
    ]);
}

it('leaves the Carbon-serialised start/end of the authenticated endpoints untouched', function () {
    $event = meetupEventForResource();

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data['start'])->toBe('2026-09-16T17:00:00.000000Z')
        ->and($data['end'])->toBe('2026-09-16T20:30:00.000000Z');
});

it('leaves the Carbon-serialised start/end of the mine list untouched as well', function () {
    $event = meetupEventForResource();

    $row = collect($this->getJson('/api/my-meetup-events')->assertOk()->json('data'))
        ->firstWhere('id', $event->id);

    expect($row['start'])->toBe('2026-09-16T17:00:00.000000Z')
        ->and($row['end'])->toBe('2026-09-16T20:30:00.000000Z');
});

it('adds zone-marked ISO 8601 twins to MeetupEventResource', function () {
    $event = meetupEventForResource();

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data['start_iso'])->toBe('2026-09-16T17:00:00+00:00')
        ->and($data['end_iso'])->toBe('2026-09-16T20:30:00+00:00');
});

it('keeps end_iso present and null for an open-ended event, exactly like end', function () {
    $event = meetupEventForResource(['end' => null]);

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data)->toHaveKey('end_iso')
        ->and($data['end'])->toBeNull()
        ->and($data['end_iso'])->toBeNull()
        ->and($data['start_iso'])->toBe('2026-09-16T17:00:00+00:00');
});

it('emits the same start_iso for one event through the resource and through the list endpoint', function () {
    /*
     * The point of the whole issue: one instant, one new spelling — not a fourth. The
     * two values are asserted AGAINST EACH OTHER, not each against its own literal,
     * because a per-endpoint literal test passes while the two silently diverge. The
     * literal is pinned once afterwards so a pair of nulls cannot satisfy the equality.
     */
    $event = meetupEventForResource();

    $resource = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');
    $listRow = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($resource['start_iso'])->toBe($listRow['start_iso'])
        ->and($resource['end_iso'])->toBe($listRow['end_iso'])
        ->and($listRow['start_iso'])->toBe('2026-09-16T17:00:00+00:00');
});

it('states one instant in exactly two spellings per endpoint, and the new one is shared', function () {
    // The deprecated spellings still differ per endpoint — that is the migration debt
    // #71 and #85 both left in place on purpose. What may NOT differ is the `_iso` form.
    $event = meetupEventForResource();

    $resource = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');
    $listRow = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect([$resource['start'], $listRow['start']])
        ->toBe(['2026-09-16T17:00:00.000000Z', '2026-09-16 17:00'])
        ->and(array_unique([$resource['start_iso'], $listRow['start_iso']]))
        ->toBe(['2026-09-16T17:00:00+00:00']);
});

/*
 * Issue #125 — the same defect, the last three fields of this resource:
 * `recurrence_end_date`, `created_at` and `updated_at` went out as raw Carbon in the
 * `.000000Z` form after #85 had converged `start` / `end`. Same additive treatment.
 *
 * The `_iso` literals below are the CONVERTED form of the `.000000Z` literals in the
 * same test, and both were measured against the pre-change resource rather than copied
 * from the issue.
 *
 * One thing to be honest about, because it decides what these tests can prove:
 * `GET /api/meetup-events` — the hand-built mapping #85's cross-consumer test compared
 * against — emits none of these three fields at all (measured: the key set of a list row
 * contains no `recurrence_end_date`, `created_at` or `updated_at`). There is therefore no
 * second PRODUCER of them to drift from; every consumer named in #125 goes through this
 * one resource. What the cross-endpoint test below pins is that all four of those
 * consumers emit the identical string, so a future hand-built mapping — the way the list
 * endpoint is built — cannot introduce a second spelling unnoticed.
 */

function meetupEventWithSeriesEnd(array $attributes = []): MeetupEvent
{
    return meetupEventForResource([
        // A real value from the web editor's path, not a round date: the editor stores
        // the END OF THE CHOSEN DAY in the organiser's timezone converted to UTC, so
        // `2026-12-31` picked in Europe/Berlin lands on 22:59:59. A literal of
        // `00:00:00` would let a date-only truncation pass unnoticed.
        'recurrence_end_date' => '2026-12-31 22:59:59',
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-02-03 04:05:06',
        ...$attributes,
    ]);
}

it('leaves the Carbon-serialised series end and record timestamps untouched', function () {
    $event = meetupEventWithSeriesEnd();

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data['recurrence_end_date'])->toBe('2026-12-31T22:59:59.000000Z')
        ->and($data['created_at'])->toBe('2026-01-02T03:04:05.000000Z')
        ->and($data['updated_at'])->toBe('2026-02-03T04:05:06.000000Z');
});

it('adds zone-marked ISO 8601 twins for the series end and the record timestamps', function () {
    $event = meetupEventWithSeriesEnd();

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data['recurrence_end_date_iso'])->toBe('2026-12-31T22:59:59+00:00')
        ->and($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($data['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');
});

it('keeps the series end at full precision instead of truncating it to a date', function () {
    /*
     * `recurrence_end_date` READS like a date and is a `datetime` column. The time of
     * day is load-bearing: end-of-day in a western zone converts to the NEXT calendar
     * day in UTC, so a client that wants the day the organiser picked has to format
     * this instant in the organiser's zone. Truncating here would destroy that.
     */
    $event = meetupEventWithSeriesEnd(['recurrence_end_date' => '2027-01-01 04:59:59']);

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data['recurrence_end_date_iso'])->toBe('2027-01-01T04:59:59+00:00');
});

it('keeps recurrence_end_date_iso present and null for an event without a series end', function () {
    $event = meetupEventWithSeriesEnd(['recurrence_end_date' => null]);

    $data = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($data)->toHaveKey('recurrence_end_date_iso')
        ->and($data['recurrence_end_date'])->toBeNull()
        ->and($data['recurrence_end_date_iso'])->toBeNull()
        ->and($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00');
});

it('emits one spelling of the series end and the record timestamps across every endpoint that carries them', function () {
    /*
     * The four HTTP consumers issue #125 names, in one test and compared AGAINST EACH
     * OTHER rather than each against its own literal — a per-endpoint literal test
     * passes while two of them silently diverge. The literals are pinned once at the
     * end so a set of nulls cannot satisfy the equality.
     *
     * The clock is FROZEN around the pair and moved on by hand, so that `created_at`
     * and `updated_at` are literals rather than "whatever the wall clock said". Left to
     * the real clock, POST and PATCH land in the same second whenever the machine is
     * fast enough, and any assertion that the two differ is a coin toss (issue #142).
     */
    $this->travelTo(Carbon::parse('2026-05-01 10:00:00'));

    $created = $this->postJson('/api/meetup-events', [
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
        'recurrence_end_date' => '2026-12-31 22:59:59',
    ])->assertCreated()->json('data');

    $this->travelTo(Carbon::parse('2026-05-01 10:00:07'));

    $patched = $this->patchJson("/api/meetup-events/{$created['id']}", [
        'description' => 'Changed, so updated_at moves.',
    ])->assertOk()->json('data');

    $shown = $this->getJson("/api/my-meetup-events/{$created['id']}")->assertOk()->json('data');

    $listed = collect($this->getJson('/api/my-meetup-events')->assertOk()->json('data'))
        ->firstWhere('id', $created['id']);

    foreach (['recurrence_end_date_iso', 'created_at_iso', 'updated_at_iso'] as $field) {
        expect(array_unique([$patched[$field], $shown[$field], $listed[$field]]))
            ->toHaveCount(1, "{$field} differs between PATCH, mineShow and the mine list");
    }

    expect($shown['recurrence_end_date_iso'])->toBe('2026-12-31T22:59:59+00:00')
        ->and($shown['created_at_iso'])->toBe('2026-05-01T10:00:00+00:00')
        ->and($created['created_at_iso'])->toBe('2026-05-01T10:00:00+00:00')
        ->and($shown['updated_at_iso'])->toBe('2026-05-01T10:00:07+00:00')
        ->and($created['updated_at_iso'])->toBe('2026-05-01T10:00:00+00:00');
});
