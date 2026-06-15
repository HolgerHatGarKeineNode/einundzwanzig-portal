<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('courseEvent'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'venue_id' => ['sometimes', 'required', 'integer', 'exists:venues,id'],
            'from' => ['sometimes', 'required', 'date'],
            'to' => ['sometimes', 'required', 'date', 'after_or_equal:from'],
            'link' => ['sometimes', 'required', 'url', 'max:255'],
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
