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
            'city_id' => $this->city_id,
            'location' => $this->location,
            'osm_type' => $this->osm_type,
            'osm_id' => $this->osm_id,
            'osm_name' => $this->osm_name,
            'osm_address' => $this->osm_address,
            'osm_lat' => $this->osm_lat,
            'osm_lon' => $this->osm_lon,
            'from' => $this->from,
            'to' => $this->to,
            'link' => $this->link,
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
