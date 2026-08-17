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
            /** Start of the event, UTC. */
            'from' => $this->from,
            /** End of the event, UTC. */
            'to' => $this->to,
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
