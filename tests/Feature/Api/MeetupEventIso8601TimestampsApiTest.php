<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Carbon;

/*
 * Issue #71: GET /api/meetup-events and GET /api/mobile/meetups emitted `Y-m-d H:i`
 * in UTC with NO zone marker, so a consumer could not tell UTC from the organiser's
 * zone from its own.
 *
 * The fix is ADDITIVE: an ISO 8601 twin next to each legacy field, same naming scheme
 * (`<field>_iso`) and same format on both endpoints. The legacy fields stay for the
 * shipped mobile client, which is why half of this file asserts that they did NOT move.
 *
 * Every assertion is on the literal emitted string. `assertJsonStructure()` or
 * `toHaveKey()` would stay green through exactly the silent format change this issue
 * is about.
 */

beforeEach(function () {
    // Frozen clock: both endpoints filter against now(), and the expected strings
    // below are literals, not re-derived from the same call under test.
    $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));

    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'visible_on_map' => true,
    ]);
});

it('leaves the legacy start/end strings of GET /api/meetup-events untouched', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
        'end' => '2026-09-16 20:30:00',
    ]);

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($row['start'])->toBe('2026-09-16 17:00')
        ->and($row['end'])->toBe('2026-09-16 20:30');
});

it('adds zone-marked ISO 8601 twins to GET /api/meetup-events', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
        'end' => '2026-09-16 20:30:00',
    ]);

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($row['start_iso'])->toBe('2026-09-16T17:00:00+00:00')
        ->and($row['end_iso'])->toBe('2026-09-16T20:30:00+00:00');
});

it('keeps end_iso present and null for an open-ended event, exactly like end', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
        'end' => null,
    ]);

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($row)->toHaveKey('end_iso')
        ->and($row['end'])->toBeNull()
        ->and($row['end_iso'])->toBeNull()
        ->and($row['start_iso'])->toBe('2026-09-16T17:00:00+00:00');
});

it('leaves the legacy next_event_start string of GET /api/mobile/meetups untouched', function () {
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
    ]);

    $row = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())
        ->firstWhere('name', $this->meetup->name);

    expect($row['next_event_start'])->toBe('2026-09-16 17:00');
});

it('adds a zone-marked ISO 8601 twin to GET /api/mobile/meetups', function () {
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
    ]);

    $row = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())
        ->firstWhere('name', $this->meetup->name);

    expect($row['next_event_start_iso'])->toBe('2026-09-16T17:00:00+00:00');
});

it('keeps next_event_start_iso present and null without an upcoming event', function () {
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-08-01 17:00:00',
    ]);

    $row = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())
        ->firstWhere('name', $this->meetup->name);

    expect($row)->toHaveKey('next_event_start_iso')
        ->and($row['next_event_start'])->toBeNull()
        ->and($row['next_event_start_iso'])->toBeNull();
});

it('emits byte-identical ISO 8601 for the same instant on both endpoints', function () {
    // The issue's third checkbox: a consumer reading one format from the list endpoint
    // and another from the detail endpoint would be worse than the current ambiguity.
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => '2026-09-16 17:00:00',
    ]);

    $detail = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);
    $mobile = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())
        ->firstWhere('name', $this->meetup->name);

    expect($mobile['next_event_start_iso'])->toBe($detail['start_iso'])
        ->and($detail['start_iso'])->toBe('2026-09-16T17:00:00+00:00');
});
