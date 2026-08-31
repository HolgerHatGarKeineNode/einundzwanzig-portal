<?php

namespace App\Http\Requests\Api;

use App\Models\WebhookSubscription;
use App\Rules\PublicHttpsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WebhookSubscription::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new PublicHttpsUrl],
            'resources' => ['required', 'array', 'min:1'],
            /** One or more of: meetup, meetup-event. */
            'resources.*' => ['string', Rule::in(config('einundzwanzig.webhooks.allowed_resources'))],
            /** Whether the owner can retrieve their own secret again after creation. Defaults to false (today's one-time reveal). */
            'reveal_secret' => ['sometimes', 'boolean'],
        ];
    }
}
