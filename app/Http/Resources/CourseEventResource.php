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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'venue_id' => $this->venue_id,
            'from' => $this->from,
            'to' => $this->to,
            'link' => $this->link,
            'course' => $this->whenLoaded('course', fn (): array => [
                'id' => $this->course->id,
                'name' => $this->course->name,
            ]),
            'venue' => $this->whenLoaded('venue', fn (): array => [
                'id' => $this->venue->id,
                'name' => $this->venue->name,
            ]),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
