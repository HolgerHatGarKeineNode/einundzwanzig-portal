<?php

namespace App\Http\Requests\Api;

use App\Rules\ValidNpub;
use Illuminate\Foundation\Http\FormRequest;

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
            'npub' => ['required', 'string', new ValidNpub],
        ];
    }
}
