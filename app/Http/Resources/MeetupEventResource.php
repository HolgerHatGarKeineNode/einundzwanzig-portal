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
            'title' => $this->title,
            'start' => $this->start,
            // End of this occurrence — recurrence_end_date below is when the series stops.
            'end' => $this->end,
            'location' => $this->location,
            // The map place, when one was picked; `location` carries the address either way.
            'osm_type' => $this->osm_type,
            'osm_id' => $this->osm_id,
            'osm_name' => $this->osm_name,
            'osm_address' => $this->osm_address,
            'osm_lat' => $this->osm_lat,
            'osm_lon' => $this->osm_lon,
            'description' => $this->description,
            'link' => $this->link,
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
