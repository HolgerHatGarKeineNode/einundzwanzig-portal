<?php

use App\Http\Resources\CourseEventResource;
use App\Models\Course;
use App\Models\CourseEvent;
use Illuminate\Support\Carbon;

/*
 * Issue #145: GET /api/courses/{course} builds its event list by hand instead of going
 * through CourseEventResource, so it never received the `from_iso` / `to_iso` twins the
 * follow-up to #125 added there, and emitted only Carbon's `.000000Z` form.
 *
 * The fix is ADDITIVE, exactly like #71 and #85: the twins are added, the deprecated
 * raw fields stay byte for byte. Every assertion is on the literal emitted string.
 */

beforeEach(function () {
    // Frozen clock: show() filters against now(), and the expected strings are literals.
    $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));

    $this->course = Course::factory()->create();
    $this->event = CourseEvent::factory()->create([
        'course_id' => $this->course->id,
        'from' => '2026-09-16 17:00:00',
        'to' => '2026-09-16 20:30:00',
    ]);
});

it('leaves the deprecated from/to strings of GET /api/courses/{course} untouched', function () {
    $this->getJson('/api/courses/'.$this->course->id)
        ->assertOk()
        ->assertJsonPath('events.0.from', '2026-09-16T17:00:00.000000Z')
        ->assertJsonPath('events.0.to', '2026-09-16T20:30:00.000000Z');
});

it('adds zone-marked ISO 8601 twins to GET /api/courses/{course}', function () {
    $this->getJson('/api/courses/'.$this->course->id)
        ->assertOk()
        ->assertJsonPath('events.0.from_iso', '2026-09-16T17:00:00+00:00')
        ->assertJsonPath('events.0.to_iso', '2026-09-16T20:30:00+00:00');
});

it('emits the same timestamp strings as CourseEventResource for the same event', function () {
    // The hand-built list and the resource are two producers of one shape; this is the
    // guard that keeps them from drifting apart again (pattern from #85).
    $detail = $this->getJson('/api/courses/'.$this->course->id)->assertOk()->json('events.0');
    $resource = json_decode(CourseEventResource::make($this->event->fresh())->toJson(), true);

    foreach (['from', 'to', 'from_iso', 'to_iso'] as $field) {
        expect($detail[$field])->toBe($resource[$field]);
    }
});
