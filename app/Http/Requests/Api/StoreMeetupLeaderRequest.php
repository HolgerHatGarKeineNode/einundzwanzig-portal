<?php

namespace App\Http\Requests\Api;

use App\Rules\ValidNpub;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Appoints another leader for a meetup via Nostr npub. Only an existing leader
 * (or the creator/super admin) may do this (manageLeaders). The npub must be a
 * valid bech32-encoded public key.
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
