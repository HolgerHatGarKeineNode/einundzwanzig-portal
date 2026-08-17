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
            /**
             * The course this date belongs to. Find ids via `GET /api/courses`.
             *
             * @example 42
             */
            'course_id' => ['required', 'integer', 'exists:courses,id'],

            /**
             * The town the event takes place in. Find ids via `GET /api/cities`.
             *
             * Required: courses are filtered by country through their events' city, so an
             * event without one would not appear in any listing a visitor can reach.
             *
             * @example 361
             */
            'city_id' => ['required', 'integer', 'exists:cities,id'],

            /**
             * The address in plain words, as the organiser would write it on a flyer.
             *
             * This is the human answer and always applies — "Bürgerhaus, Fischergasse 1" as
             * much as "room to be confirmed". The `osm_*` fields are the machine-readable
             * addition on top, and they are optional.
             *
             * @example Bürgerhaus Neumarkt, Fischergasse 1
             */
            'location' => ['nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(),

            /**
             * Start of the event, ISO 8601. Stored and returned in UTC.
             *
             * @example 2026-09-01T17:00:00+02:00
             */
            'from' => ['required', 'date'],

            /**
             * End of the event, ISO 8601. Must not precede `from`.
             *
             * @example 2026-09-01T20:00:00+02:00
             */
            'to' => ['required', 'date', 'after_or_equal:from'],

            /**
             * Where to read more or sign up.
             *
             * @example https://einundzwanzig.space/courses/bitcoin-basics
             */
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
