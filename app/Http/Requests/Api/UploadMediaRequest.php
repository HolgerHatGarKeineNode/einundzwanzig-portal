<?php

namespace App\Http\Requests\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->boundModel();

        return $model !== null && $this->user()->can('update', $model);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'image',
                'mimes:jpeg,png,webp,avif',
                'max:5120',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * The route-bound model whose media is being replaced (meetup, lecturer, course).
     */
    protected function boundModel(): ?Model
    {
        foreach ($this->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }
}
