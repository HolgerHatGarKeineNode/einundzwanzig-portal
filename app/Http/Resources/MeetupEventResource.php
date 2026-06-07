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
            'start' => $this->start,
            'location' => $this->location,
            'description' => $this->description,
            'link' => $this->link,
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
