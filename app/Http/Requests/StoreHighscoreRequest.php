<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHighscoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'npub' => ['required', 'string', 'starts_with:npub1', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'satoshis' => ['required', 'integer', 'min:0'],
            'blocks' => ['required', 'integer', 'min:0'],
            'datetime' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'npub.required' => 'An npub is required to record the highscore.',
            'npub.starts_with' => 'The npub must start with npub1.',
            'name.string' => 'The name must be a valid string.',
            'satoshis.required' => 'Please provide the earned satoshis amount.',
            'satoshis.integer' => 'Satoshis must be a whole number.',
            'blocks.required' => 'Please provide the number of blocks.',
            'blocks.integer' => 'Blocks must be a whole number.',
            'datetime.required' => 'Please provide when the score was achieved.',
            'datetime.date' => 'The datetime value must be a valid date.',
        ];
    }
}
