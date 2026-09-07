<?php

namespace App\Http\Resources;

use App\Models\CourseEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseEvent
 */
class CourseEventResource extends JsonResource
{
    /**
     * BREAKING CHANGE for API consumers: `venue_id` and the nested `venue` object are
     * gone for good — the Venue model was removed, not renamed, so there is nothing to
     * keep them pointing at and no deprecation window that would help. What used to be
     * `venue.name` is now `location` (free text), `venue.city` is now `city`, and the
     * street address is part of `location`. Clients that need the exact spot on a map
     * read the `osm_*` fields, which are null whenever the place was never matched.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            /** The town this date takes place in. */
            'city_id' => $this->city_id,
            /**
             * The address in plain words, as the organiser wrote it. Always the readable
             * answer — including "room to be confirmed" — while the `osm_*` fields below
             * are the machine-readable addition and may be null.
             */
            'location' => $this->location,
            /** `node`, `way` or `relation`; null when no map place was picked. */
            'osm_type' => $this->osm_type,
            /**
             * OpenStreetMap object id. With `osm_type` it forms the permanent link:
             * `https://www.openstreetmap.org/{osm_type}/{osm_id}`.
             */
            'osm_id' => $this->osm_id,
            /** The place name as OpenStreetMap knows it — a copy, so renames there do not erase it. */
            'osm_name' => $this->osm_name,
            /** The full address line from OpenStreetMap. */
            'osm_address' => $this->osm_address,
            /** Latitude in decimal degrees, 7 decimals. Serialised as a string to keep the precision. */
            'osm_lat' => $this->osm_lat,
            /** Longitude in decimal degrees, 7 decimals. Serialised as a string to keep the precision. */
            'osm_lon' => $this->osm_lon,
            /**
             * DEPRECATED (follow-up to #125): start of the event, UTC, in Carbon's
             * default JSON form — `2026-09-16T17:00:00.000000Z`, microseconds and a `Z`
             * suffix. Read `from_iso` below instead, which is the form the rest of the
             * API uses.
             *
             * Kept unchanged, byte for byte, until the consumers of these endpoints and
             * of the MCP tools have moved over — dropping it is a breaking change and
             * belongs to a coordinated client release, not to a later edit here. Both
             * fields describe the same instant, so a client can migrate one at a time.
             */
            'from' => $this->from,
            /**
             * DEPRECATED (follow-up to #125), exactly like `from` above and on the same
             * terms: end of the event, UTC, as `2026-09-16T20:30:00.000000Z`. Read
             * `to_iso`.
             */
            'to' => $this->to,
            /**
             * The zone-marked replacement for `from` (follow-up to #125):
             * `2026-09-16T17:00:00+00:00` — ISO 8601 with a numeric OFFSET, not the `Z`
             * shorthand and without microseconds. Identical format and identical
             * `<field>_iso` naming to `start_iso` on {@see MeetupEventResource} since
             * issue #85, so a client reading a course event and a meetup event parses
             * ONE form.
             *
             * This is the CONVERT case, not the reinterpret one: `from` is a `datetime`
             * cast, so the value arrives as an App\Support\Carbon that already knows its
             * zone, and `->setTimezone('UTC')` moves that known instant to UTC.
             * App\Support\Carbon extends CarbonImmutable, so the conversion returns a
             * new instance and cannot move the deprecated field.
             *
             * A no-op in value today (config('app.timezone') is UTC, and SetTimezone
             * runs on the web middleware group only — never on an API or MCP request).
             * Spelling the conversion out makes `+00:00` a promise of this resource
             * instead of a side effect of that configuration.
             *
             * No `?->`, unlike the `_iso` twins below: `from` is NOT NULL in the schema
             * (migration 2022_12_01_145955 declares `dateTime('from')` without
             * `nullable()`), so there is no null to guard and a null here would be a
             * broken row rather than a case to serialise.
             */
            'from_iso' => $this->from->setTimezone('UTC')->toIso8601String(),
            /**
             * The zone-marked replacement for `to` (follow-up to #125):
             * `2026-09-16T20:30:00+00:00`, converted exactly like `from_iso` above and
             * likewise without `?->` — `to` is NOT NULL in the same migration. A course
             * event always carries an end; unlike a meetup event, it cannot be
             * open-ended.
             */
            'to_iso' => $this->to->setTimezone('UTC')->toIso8601String(),
            'link' => $this->link,
            /** Topic tags. Only present when the relation was loaded; see the Tag schema for the translated names. */
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'course' => $this->whenLoaded('course', fn (): array => [
                'id' => $this->course->id,
                'name' => $this->course->name,
            ]),
            'city' => $this->whenLoaded('city', fn (): ?array => $this->city === null ? null : [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ]),
            'created_by' => $this->created_by,
            /**
             * DEPRECATED (follow-up to #125): when this course event was first written,
             * as `2026-01-02T03:04:05.000000Z`. Read `created_at_iso`. Kept unchanged,
             * byte for byte, on the same terms as `from` above.
             */
            'created_at' => $this->created_at,
            /**
             * The zone-marked replacement for `created_at` (follow-up to #125):
             * `2026-01-02T03:04:05+00:00`, converted exactly like `from_iso` above.
             *
             * Metadata rather than event data, and converged anyway: ONE spelling holds
             * across this whole resource, so no consumer has to learn which field
             * carries which form. `?->` here, unlike `from_iso` — the timestamp columns
             * ARE nullable, and a row written with timestamps disabled has no value.
             */
            'created_at_iso' => $this->created_at?->setTimezone('UTC')->toIso8601String(),
            /**
             * DEPRECATED (follow-up to #125): when this course event was last written,
             * as `2026-02-03T04:05:06.000000Z`. Read `updated_at_iso`. Kept unchanged,
             * byte for byte, on the same terms as `from` above.
             */
            'updated_at' => $this->updated_at,
            /** The zone-marked replacement for `updated_at` (follow-up to #125), on the same terms as `created_at_iso`. */
            'updated_at_iso' => $this->updated_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
