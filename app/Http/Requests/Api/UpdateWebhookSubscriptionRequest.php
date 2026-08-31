<?php

namespace App\Http\Requests\Api;

use App\Rules\PublicHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('webhookSubscription'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'required', 'string', 'max:2048', new PublicHttpsUrl],
            'resources' => ['sometimes', 'required', 'array', 'min:1'],
            /** One or more of: meetup, meetup-event. */
            'resources.*' => ['string', Rule::in(config('einundzwanzig.webhooks.allowed_resources'))],
            /** Pause (false) or resume (true) delivery. Resuming a system-disabled subscription also clears its failure count. */
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
