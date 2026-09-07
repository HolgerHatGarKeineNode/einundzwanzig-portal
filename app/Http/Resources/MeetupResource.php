<?php

namespace App\Http\Resources;

use App\Models\Meetup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meetup
 */
class MeetupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'city_id' => $this->city_id,
            'intro' => $this->intro,
            'telegram_link' => $this->telegram_link,
            'webpage' => $this->webpage,
            'twitter_username' => $this->twitter_username,
            'matrix_group' => $this->matrix_group,
            'nostr' => $this->nostr,
            'simplex' => $this->simplex,
            'signal' => $this->signal,
            'community' => $this->community,
            'visible_on_map' => $this->visible_on_map,
            'is_active' => $this->is_active,
            'rsvp_enabled' => $this->rsvp_enabled,
            'attendees_public' => $this->attendees_public,
            'nostr_publishing_enabled' => $this->nostr_publishing_enabled,
            // Only set when the meetup_user pivot is loaded (e.g. via
            // /api/my-meetups). Tells the app whether the token holder is a leader of
            // this meetup (may edit + manage leaders).
            'is_leader' => $this->whenPivotLoaded('meetup_user', fn (): bool => (bool) $this->pivot->is_leader),
            'logo' => $this->getFirstMediaUrl('logo', 'thumb'),
            /**
             * DEPRECATED (follow-up to #125): the start of the most recent PAST event of
             * this meetup, recomputed nightly together with `is_active`
             * ({@see Meetup::recalculateActivity()}), UTC, in Carbon's default JSON form —
             * `2026-03-04T05:06:07.000000Z`, microseconds and a `Z` suffix. Read
             * `last_event_at_iso` below instead, which is the form the rest of the API
             * uses.
             *
             * Kept unchanged, byte for byte, until the consumers of these endpoints and
             * of the MCP tools have moved over — dropping it is a breaking change and
             * belongs to a coordinated client release, not to a later edit here. Both
             * fields describe the same instant, so a client can migrate one at a time.
             */
            'last_event_at' => $this->last_event_at,
            /**
             * The zone-marked replacement for `last_event_at` (follow-up to #125):
             * `2026-03-04T05:06:07+00:00` — ISO 8601 with a numeric OFFSET, not the `Z`
             * shorthand and without microseconds. Identical format and identical
             * `<field>_iso` naming to the twins {@see MeetupEventResource} carries since
             * issues #85 and #125, so a client reading more than one resource of this
             * API parses ONE form.
             *
             * This is the CONVERT case, not the reinterpret one: `last_event_at` is a
             * `datetime` cast, so the value arrives as an App\Support\Carbon that
             * already knows its zone, and `->setTimezone('UTC')` moves that known
             * instant to UTC. App\Support\Carbon extends CarbonImmutable, so the
             * conversion returns a new instance and cannot move the deprecated field.
             *
             * A no-op in value today (config('app.timezone') is UTC, and SetTimezone
             * runs on the web middleware group only — never on an API or MCP request).
             * Spelling the conversion out makes `+00:00` a promise of this resource
             * instead of a side effect of that configuration.
             *
             * `?->` because the column is nullable (migration
             * 2026_05_17_153816) — a meetup that has never held an event has no value.
             * Always present, and null in that case — never absent, so a client can tell
             * "no event yet" from "this endpoint does not serve one".
             */
            'last_event_at_iso' => $this->last_event_at?->setTimezone('UTC')->toIso8601String(),
            'created_by' => $this->created_by,
            /**
             * DEPRECATED (follow-up to #125): when this meetup was first written, as
             * `2026-01-02T03:04:05.000000Z`. Read `created_at_iso`. Kept unchanged, byte
             * for byte, on the same terms as `last_event_at` above.
             */
            'created_at' => $this->created_at,
            /**
             * The zone-marked replacement for `created_at` (follow-up to #125):
             * `2026-01-02T03:04:05+00:00`, converted exactly like `last_event_at_iso`
             * above.
             *
             * Metadata rather than meetup data, and converged anyway: ONE spelling holds
             * across this whole resource, so no consumer has to learn which field
             * carries which form. `?->` because the column is nullable — a row written
             * with timestamps disabled has no value, and a resource must not fatal on
             * one.
             */
            'created_at_iso' => $this->created_at?->setTimezone('UTC')->toIso8601String(),
            /**
             * DEPRECATED (follow-up to #125): when this meetup was last written, as
             * `2026-02-03T04:05:06.000000Z`. Read `updated_at_iso`. Kept unchanged, byte
             * for byte, on the same terms as `last_event_at` above.
             */
            'updated_at' => $this->updated_at,
            /** The zone-marked replacement for `updated_at` (follow-up to #125), on the same terms as `created_at_iso`. */
            'updated_at_iso' => $this->updated_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
