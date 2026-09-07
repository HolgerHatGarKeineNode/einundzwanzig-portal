<?php

namespace App\Http\Resources;

use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lecturer
 */
class LecturerResource extends JsonResource
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
            'subtitle' => $this->subtitle,
            'intro' => $this->intro,
            'description' => $this->description,
            'active' => $this->active,
            'website' => $this->website,
            'twitter_username' => $this->twitter_username,
            'nostr' => $this->nostr,
            'lightning_address' => $this->lightning_address,
            'lnurl' => $this->lnurl,
            'node_id' => $this->node_id,
            'paynym' => $this->paynym,
            'team_id' => $this->team_id,
            'avatar' => $this->getFirstMediaUrl('avatar', 'thumb'),
            'created_by' => $this->created_by,
            /**
             * DEPRECATED (follow-up to #125): when this lecturer was first written, UTC,
             * in Carbon's default JSON form — `2026-01-02T03:04:05.000000Z`,
             * microseconds and a `Z` suffix. Read `created_at_iso` below instead, which
             * is the form the rest of the API uses.
             *
             * Kept unchanged, byte for byte, until the consumers of these endpoints and
             * of the MCP tools have moved over — dropping it is a breaking change and
             * belongs to a coordinated client release, not to a later edit here. Both
             * fields describe the same instant, so a client can migrate one at a time.
             */
            'created_at' => $this->created_at,
            /**
             * The zone-marked replacement for `created_at` (follow-up to #125):
             * `2026-01-02T03:04:05+00:00` — ISO 8601 with a numeric OFFSET, not the `Z`
             * shorthand and without microseconds. Identical format and identical
             * `<field>_iso` naming to the twins {@see MeetupEventResource} carries since
             * issues #85 and #125, so a client reading more than one resource of this
             * API parses ONE form.
             *
             * This is the CONVERT case, not the reinterpret one: `created_at` is a
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
             * `?->` because the column is nullable (`timestamps()` writes it nullable) —
             * a row written with timestamps disabled has no value, and a resource must
             * not fatal on one. Always present, and null in that case — never absent.
             */
            'created_at_iso' => $this->created_at?->setTimezone('UTC')->toIso8601String(),
            /**
             * DEPRECATED (follow-up to #125): when this lecturer was last written, as
             * `2026-02-03T04:05:06.000000Z`. Read `updated_at_iso`. Kept unchanged, byte
             * for byte, on the same terms as `created_at` above.
             */
            'updated_at' => $this->updated_at,
            /** The zone-marked replacement for `updated_at` (follow-up to #125), on the same terms as `created_at_iso`. */
            'updated_at_iso' => $this->updated_at?->setTimezone('UTC')->toIso8601String(),
        ];
    }
}
