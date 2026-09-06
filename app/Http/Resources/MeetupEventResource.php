<?php

namespace App\Http\Resources;

use App\Models\MeetupEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeetupEvent
 */
class MeetupEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meetup_id' => $this->meetup_id,
            /** Headline for this one date; null means the meetup's own name applies. */
            'title' => $this->title,
            /**
             * DEPRECATED (issue #85): start of the event, UTC, in Carbon's default JSON
             * form — `2026-09-16T17:00:00.000000Z`, microseconds and a `Z` suffix. That
             * is a THIRD spelling of one instant beside the `2026-09-16 17:00` and
             * `2026-09-16T17:00:00+00:00` the list endpoints emit; read `start_iso`
             * below instead, which is the form the rest of the API uses.
             *
             * Kept unchanged, byte for byte, until the consumers of these endpoints and
             * of the MCP tools have moved over — dropping it is a breaking change and
             * belongs to a coordinated client release, not to a later edit here. Both
             * fields describe the same instant, so a client can migrate one at a time.
             */
            'start' => $this->start,
            /**
             * DEPRECATED (issue #85), exactly like `start` above and on the same terms:
             * end of THIS occurrence, UTC, as `2026-09-16T20:30:00.000000Z`. Null for
             * open-ended meetups; `recurrence_end_date` ends the series. Read `end_iso`.
             */
            'end' => $this->end,
            /**
             * The zone-marked replacement for `start` (issue #85):
             * `2026-09-16T17:00:00+00:00` — ISO 8601 with a numeric OFFSET, not the `Z`
             * shorthand and without microseconds. Identical format and identical
             * `<field>_iso` naming to `start_iso` on `GET /api/meetup-events` and
             * `next_event_start_iso` on `GET /api/mobile/meetups` (issue #71), so a
             * client reading a list and then a single event parses ONE form.
             *
             * This is the CONVERT case, not the reinterpret one. `start` is a `datetime`
             * cast, so the value arrives as an App\Support\Carbon that already knows its
             * zone, and `->setTimezone('UTC')` moves that known instant to UTC.
             * `Carbon::parse($value, 'UTC')` — what MobileMeetupListController needs,
             * because its correlated subquery hands it a bare `Y-m-d H:i:s` string with
             * no zone at all — would be the wrong operation here: this value is never a
             * zoneless string. App\Support\Carbon extends CarbonImmutable, so the
             * conversion returns a new instance and cannot move the deprecated field.
             *
             * A no-op in value today (config('app.timezone') is UTC, and SetTimezone
             * runs on the web middleware group only — never on an API or MCP request).
             * Spelling the conversion out makes `+00:00` a promise of this resource
             * instead of a side effect of that configuration.
             */
            'start_iso' => $this->start->setTimezone('UTC')->toIso8601String(),
            /**
             * The zone-marked replacement for `end` (issue #85):
             * `2026-09-16T20:30:00+00:00`, converted exactly like `start_iso`. Always
             * present, and null for an open-ended event — never absent, so a client can
             * tell "no end time" from "this endpoint does not serve one".
             */
            'end_iso' => $this->end?->setTimezone('UTC')->toIso8601String(),
            /**
             * The address in plain words, as the organiser wrote it. Always the readable
             * answer — including "watch the Signal group" — while the `osm_*` fields below
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
            'description' => $this->description,
            /**
             * DEPRECATED (issue #70): the FIRST of `links` below, or null. An event can
             * carry up to five links since #70, and this field only ever shows one of
             * them. It is kept, unchanged, so existing clients do not break; read
             * `links` instead. It is also still accepted on write, where it addresses
             * the first entry of the list only — see the `link` field of the create and
             * update requests (issue #108).
             */
            'link' => $this->link,
            /**
             * Every link the organiser attached, in their order, each with an optional
             * `label` — "Meetup.com", "Telegram", … The label is null when there is
             * none, never an empty string, and the list is `[]` when there are no links.
             *
             * @var list<array{url: string, label: string|null}>
             */
            'links' => $this->linkList(),
            /** Topic tags. Only present when the relation was loaded; see the Tag schema for the translated names. */
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'recurrence_type' => $this->recurrence_type,
            'recurrence_day_of_week' => $this->recurrence_day_of_week,
            'recurrence_day_position' => $this->recurrence_day_position,
            'recurrence_interval' => $this->recurrence_interval,
            /**
             * DEPRECATED (issue #125), on the same terms as `start` above: when the
             * SERIES stops producing occurrences, as `2026-12-31T22:59:59.000000Z` —
             * Carbon's default JSON form with microseconds and a `Z` suffix. Read
             * `recurrence_end_date_iso`. Null on an event that is not part of a series.
             *
             * Kept unchanged, byte for byte; removing it is a breaking change for the
             * consumers of these endpoints and of the MCP tools.
             */
            'recurrence_end_date' => $this->recurrence_end_date,
            /**
             * The zone-marked replacement for `recurrence_end_date` (issue #125):
             * `2026-12-31T22:59:59+00:00`, converted exactly like `start_iso` above and
             * for the same reason — `recurrence_end_date` is a `datetime` cast, so the
             * value arrives as an App\Support\Carbon that already knows its zone.
             * Always present, and null for an event without a series end — never
             * absent, exactly like `end_iso`.
             *
             * A TIMESTAMP, not a calendar date, however much the name reads like one.
             * The column is `datetime` (migration 2026_01_17_163021), and the web
             * editor writes the END OF THE CHOSEN DAY in the organiser's own timezone,
             * converted to UTC — `2026-12-31` picked in Europe/Berlin is stored as
             * `2026-12-31 22:59:59`, and the same day picked in America/New_York is
             * stored as `2027-01-01 04:59:59`. The API accepts a bare `Y-m-d` as well
             * (StoreMeetupEventRequest rule `date`), which lands on midnight UTC. So a
             * client that wants the DAY the organiser meant must format this instant in
             * that organiser's zone; formatting it in its own can move it by a day.
             * Truncating to a date here would destroy the information needed to do so.
             */
            'recurrence_end_date_iso' => $this->recurrence_end_date?->setTimezone('UTC')->toIso8601String(),
            'created_by' => $this->created_by,
            /**
             * DEPRECATED (issue #125): when this event was first written, as
             * `2026-01-02T03:04:05.000000Z`. Read `created_at_iso`. Kept unchanged,
             * byte for byte, on the same terms as `start` above.
             */
            'created_at' => $this->created_at,
            /**
             * The zone-marked replacement for `created_at` (issue #125):
             * `2026-01-02T03:04:05+00:00`, converted exactly like `start_iso` above.
             *
             * Metadata rather than event data, and converged anyway: ONE spelling holds
             * across this whole resource, so no consumer has to learn which field
             * carries which form. `?->` because the column is nullable — a row written
             * with timestamps disabled has no value, and a resource must not fatal on
             * one.
             */
            'created_at_iso' => $this->created_at?->setTimezone('UTC')->toIso8601String(),
            /**
             * DEPRECATED (issue #125): when this event was last written, as
             * `2026-02-03T04:05:06.000000Z`. Read `updated_at_iso`. Kept unchanged,
             * byte for byte, on the same terms as `start` above.
             */
            'updated_at' => $this->updated_at,
            /** The zone-marked replacement for `updated_at` (issue #125), on the same terms as `created_at_iso`. */
            'updated_at_iso' => $this->updated_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
