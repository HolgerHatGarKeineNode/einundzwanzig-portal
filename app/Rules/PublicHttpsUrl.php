<?php

namespace App\Rules;

use App\Support\Webhooks\SsrfGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A webhook subscription's target URL (Issue #36): https-only, and blocked
 * from loopback/RFC1918/link-local/reserved IP ranges via {@see SsrfGuard}.
 */
class PublicHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (! str_starts_with($value, 'https://')) {
            $fail('The :attribute must use https.');

            return;
        }

        if (! SsrfGuard::isPublicUrl($value)) {
            $fail('The :attribute must resolve to a public, routable address.');
        }
    }
}
