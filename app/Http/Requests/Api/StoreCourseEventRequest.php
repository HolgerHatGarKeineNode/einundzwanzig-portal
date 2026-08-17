<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\CourseEvent;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseEventRequest extends FormRequest
{
    use ValidatesOsmPlace;

    public function authorize(): bool
    {
        return $this->user()->can('create', CourseEvent::class);
    }

    /**
     * The place of a course event is no longer a record of its own: `city_id` is the coarse
     * location, `location` the free-text address line, and the `osm_*` columns the exact
     * spot when it is known.
     *
     * The city stays required, as venue_id was. Courses are filtered by country through
     * `courseEvents.city.country`, so an event without a city is not merely vague — it
     * disappears from every list a visitor might find it in. Not knowing the room yet is
     * what `location` and the optional map place are for; not knowing the town is rare
     * enough to be worth the friction.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'location' => ['nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(),
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'link' => ['required', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'course_id.exists' => __('Der angegebene Kurs existiert nicht.'),
            'city_id.exists' => __('Die angegebene Stadt existiert nicht.'),
        ];
    }
}
