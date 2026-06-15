<?php

namespace App\Http\Requests\Api;

use App\Models\CourseEvent;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CourseEvent::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
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
            'course_id.exists' => 'Der angegebene Kurs existiert nicht.',
            'venue_id.exists' => 'Der angegebene Veranstaltungsort existiert nicht.',
        ];
    }
}
