<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

/*
 * Follow-up to #125, closing the `.000000Z` class across the remaining API resources.
 *
 * #71, #85 and #125 fixed this one resource at a time. What was left over is measured,
 * not assumed: CityResource, CourseResource, CourseEventResource, LecturerResource and
 * MeetupResource handed their timestamps through as raw Carbon, so Laravel's default
 * serialisation decided the format — `2026-01-02T03:04:05.000000Z`, microseconds and a
 * `Z` suffix, next to the `+00:00` form MeetupEventResource emits.
 *
 * THIRTEEN fields across five resources, not the ten the created_at/updated_at pairs
 * suggest: CourseEventResource also carries `from` and `to` (both `datetime` casts), and
 * MeetupResource carries `last_event_at`.
 *
 * The treatment is ADDITIVE, exactly as in #71, #85 and #125: the `_iso` twins are new
 * and carry the `+00:00` form; the Carbon-serialised fields stay byte for byte until the
 * consumers of the authenticated endpoints and of the MCP tools have moved over. Half of
 * this file therefore asserts that the old fields did NOT move.
 *
 * Every assertion is on the literal emitted string, and the deprecated literals below
 * were measured against the pre-change resources rather than copied from a brief.
 * `assertJsonStructure()` or `toHaveKey()` would stay green through exactly the silent
 * format change this is about.
 */

beforeEach(function () {
    /*
     * The clock is frozen because CourseResource has no read endpoint — it is reached
     * through PATCH, which writes `updated_at` from the wall clock. Frozen, that write
     * lands on a literal, and `created_at` / `updated_at` stay two DIFFERENT literals so
     * a swapped pair cannot pass.
     */
    $this->travelTo(Carbon::parse('2026-02-03 04:05:06'));

    $this->owner = User::factory()->create();
    Sanctum::actingAs($this->owner);

    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create([
        'country_id' => $country->id,
        'created_by' => $this->owner->id,
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-02-03 04:05:06',
    ]);
});

function lecturerForResource(array $attributes = []): Lecturer
{
    return Lecturer::factory()->create([
        'created_by' => test()->owner->id,
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-02-03 04:05:06',
        ...$attributes,
    ]);
}

function courseForResource(array $attributes = []): Course
{
    return Course::factory()->create([
        'lecturer_id' => lecturerForResource()->id,
        'created_by' => test()->owner->id,
        'created_at' => '2026-01-02 03:04:05',
        // Set to the SAME instant as created_at on purpose: the PATCH below moves it to
        // the frozen "now", so the assertion sees a value the write produced.
        'updated_at' => '2026-01-02 03:04:05',
        ...$attributes,
    ]);
}

function courseEventForResource(array $attributes = []): CourseEvent
{
    return CourseEvent::factory()->create([
        'course_id' => courseForResource()->id,
        'city_id' => test()->city->id,
        'created_by' => test()->owner->id,
        'from' => '2026-09-16 17:00:00',
        'to' => '2026-09-16 20:30:00',
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-02-03 04:05:06',
        ...$attributes,
    ]);
}

function meetupForResource(array $attributes = []): Meetup
{
    // The creator becomes leader of the meetup through the model's booted hook, which is
    // what makes it show up in `mine` / `mineShow`.
    return Meetup::factory()->create([
        'city_id' => test()->city->id,
        'created_by' => test()->owner->id,
        'last_event_at' => '2026-03-04 05:06:07',
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-02-03 04:05:06',
        ...$attributes,
    ]);
}

it('leaves the Carbon-serialised timestamps of CityResource untouched', function () {
    $data = $this->getJson("/api/my-cities/{$this->city->id}")->assertOk()->json('data');

    expect($data['created_at'])->toBe('2026-01-02T03:04:05.000000Z')
        ->and($data['updated_at'])->toBe('2026-02-03T04:05:06.000000Z');
});

it('adds zone-marked ISO 8601 twins to CityResource', function () {
    $data = $this->getJson("/api/my-cities/{$this->city->id}")->assertOk()->json('data');

    expect($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($data['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');
});

it('leaves the Carbon-serialised timestamps of CourseResource untouched', function () {
    $course = courseForResource();

    $data = $this->patchJson("/api/courses/{$course->id}", ['name' => 'Renamed course'])
        ->assertOk()->json('data');

    expect($data['created_at'])->toBe('2026-01-02T03:04:05.000000Z')
        ->and($data['updated_at'])->toBe('2026-02-03T04:05:06.000000Z');
});

it('adds zone-marked ISO 8601 twins to CourseResource', function () {
    $course = courseForResource();

    $data = $this->patchJson("/api/courses/{$course->id}", ['name' => 'Renamed course'])
        ->assertOk()->json('data');

    expect($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($data['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');
});

it('leaves the Carbon-serialised timestamps of CourseEventResource untouched', function () {
    courseEventForResource();

    $data = $this->getJson('/api/course-events')->assertOk()->json('data.0');

    expect($data['from'])->toBe('2026-09-16T17:00:00.000000Z')
        ->and($data['to'])->toBe('2026-09-16T20:30:00.000000Z')
        ->and($data['created_at'])->toBe('2026-01-02T03:04:05.000000Z')
        ->and($data['updated_at'])->toBe('2026-02-03T04:05:06.000000Z');
});

it('adds zone-marked ISO 8601 twins to CourseEventResource, event dates included', function () {
    /*
     * `from` and `to` are the two fields the original finding missed. They are the
     * EVENT's own dates, not record metadata — the fields a client actually reads — and
     * they were emitting the same `.000000Z` form as everything else here.
     */
    courseEventForResource();

    $data = $this->getJson('/api/course-events')->assertOk()->json('data.0');

    expect($data['from_iso'])->toBe('2026-09-16T17:00:00+00:00')
        ->and($data['to_iso'])->toBe('2026-09-16T20:30:00+00:00')
        ->and($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($data['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');
});

it('leaves the Carbon-serialised timestamps of LecturerResource untouched', function () {
    $lecturer = lecturerForResource();

    $data = $this->getJson("/api/my-lecturers/{$lecturer->id}")->assertOk()->json('data');

    expect($data['created_at'])->toBe('2026-01-02T03:04:05.000000Z')
        ->and($data['updated_at'])->toBe('2026-02-03T04:05:06.000000Z');
});

it('adds zone-marked ISO 8601 twins to LecturerResource', function () {
    $lecturer = lecturerForResource();

    $data = $this->getJson("/api/my-lecturers/{$lecturer->id}")->assertOk()->json('data');

    expect($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($data['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');
});

it('leaves the Carbon-serialised timestamps of MeetupResource untouched', function () {
    $meetup = meetupForResource();

    $data = $this->getJson("/api/my-meetups/{$meetup->id}")->assertOk()->json('data');

    expect($data['last_event_at'])->toBe('2026-03-04T05:06:07.000000Z')
        ->and($data['created_at'])->toBe('2026-01-02T03:04:05.000000Z')
        ->and($data['updated_at'])->toBe('2026-02-03T04:05:06.000000Z');
});

it('adds zone-marked ISO 8601 twins to MeetupResource, last_event_at included', function () {
    $meetup = meetupForResource();

    $data = $this->getJson("/api/my-meetups/{$meetup->id}")->assertOk()->json('data');

    expect($data['last_event_at_iso'])->toBe('2026-03-04T05:06:07+00:00')
        ->and($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($data['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');
});

it('keeps last_event_at_iso present and null for a meetup that never held an event', function () {
    /*
     * `last_event_at` is the one nullable field of this set that carries data rather
     * than record metadata. Present and null, never absent, so a client can tell "no
     * event yet" from "this endpoint does not serve one" — the same rule `end_iso`
     * follows on MeetupEventResource.
     */
    $meetup = meetupForResource(['last_event_at' => null]);

    $data = $this->getJson("/api/my-meetups/{$meetup->id}")->assertOk()->json('data');

    expect($data)->toHaveKey('last_event_at_iso')
        ->and($data['last_event_at'])->toBeNull()
        ->and($data['last_event_at_iso'])->toBeNull()
        ->and($data['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00');
});

it('emits one spelling across every one of these resources', function () {
    /*
     * The convergence claim itself, held over all five payloads at once rather than
     * resource by resource. Two invariants, and the second is the one that catches the
     * NEXT field somebody adds:
     *
     *  - every `_iso` value is in the numeric-offset form, never the `Z` shorthand;
     *  - every value still in the `.000000Z` form has an `_iso` twin beside it.
     *
     * A per-resource literal test stays green while a sixth field is added raw. This one
     * does not.
     */
    $courseEvent = courseEventForResource();
    $meetup = meetupForResource();
    $lecturer = lecturerForResource();

    $payloads = [
        'CityResource' => $this->getJson("/api/my-cities/{$this->city->id}")->assertOk()->json('data'),
        'CourseResource' => $this->patchJson("/api/courses/{$courseEvent->course_id}", ['name' => 'Renamed course'])
            ->assertOk()->json('data'),
        'CourseEventResource' => $this->getJson('/api/course-events')->assertOk()->json('data.0'),
        'LecturerResource' => $this->getJson("/api/my-lecturers/{$lecturer->id}")->assertOk()->json('data'),
        'MeetupResource' => $this->getJson("/api/my-meetups/{$meetup->id}")->assertOk()->json('data'),
    ];

    $offsetForm = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/';
    $carbonForm = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/';
    $checked = 0;

    foreach ($payloads as $resource => $payload) {
        foreach ($payload as $field => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (str_ends_with($field, '_iso')) {
                expect($value)->toMatch($offsetForm, "{$resource}.{$field} is not in the +00:00 form");
                $checked++;

                continue;
            }

            if (preg_match($carbonForm, $value) === 1) {
                // NOT toHaveKey($key, $message): the matcher's second parameter is the
                // expected VALUE, so a message there is asserted as the value.
                expect(array_key_exists("{$field}_iso", $payload))
                    ->toBeTrue("{$resource}.{$field} is Carbon-serialised and has no _iso twin");
                $checked++;
            }
        }
    }

    // Thirteen deprecated fields and their thirteen twins. Pinned so a payload that lost
    // its timestamps entirely cannot satisfy the loop by iterating over nothing.
    expect($checked)->toBe(26);
});
