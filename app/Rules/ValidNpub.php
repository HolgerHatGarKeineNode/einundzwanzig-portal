<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use swentel\nostr\Key\Key as NostrKey;

/**
 * Validiert einen Nostr-npub (bech32-kodierter öffentlicher Schlüssel).
 * Geteilt von der REST-API (StoreMeetupLeaderRequest) und der Leader-
 * Verwaltung im Portal-Frontend, damit beide Wege dieselbe Prüfung nutzen.
 */
class ValidNpub implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, 'npub1')) {
            $fail(__('Das ist kein gültiger npub.'));

            return;
        }

        try {
            (new NostrKey)->convertToHex($value);
        } catch (\Throwable) {
            $fail(__('Das ist kein gültiger npub.'));
        }
    }
}
