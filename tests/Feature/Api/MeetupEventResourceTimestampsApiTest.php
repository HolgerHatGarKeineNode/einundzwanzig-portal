<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
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
