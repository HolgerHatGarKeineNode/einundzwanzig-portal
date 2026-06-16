<?php

namespace App\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use swentel\nostr\Key\Key as NostrKey;

/**
 * Setzt einen weiteren Leader für ein Meetup per Nostr-npub ein. Nur ein
 * bestehender Leader (bzw. Ersteller/Super-Admin) darf das (manageLeaders).
 * Der npub muss ein gültiger bech32-kodierter öffentlicher Schlüssel sein.
 */
class StoreMeetupLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageLeaders', $this->route('meetup'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'npub' => [
                'required',
                'string',
                'starts_with:npub1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        (new NostrKey)->convertToHex((string) $value);
                    } catch (\Throwable) {
                        $fail(__('Das ist kein gültiger npub.'));
                    }
                },
            ],
        ];
    }
}
