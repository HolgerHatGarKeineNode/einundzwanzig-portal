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
            /** Start of the event, UTC. */
            'start' => $this->start,
            /** End of THIS occurrence, UTC. Null for open-ended meetups; `recurrence_end_date` ends the series. */
            'end' => $this->end,
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
            'link' => $this->link,
            /** Topic tags. Only present when the relation was loaded; see the Tag schema for the translated names. */
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'recurrence_type' => $this->recurrence_type,
            'recurrence_day_of_week' => $this->recurrence_day_of_week,
            'recurrence_day_position' => $this->recurrence_day_position,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_end_date' => $this->recurrence_end_date,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
