<?php

namespace App\Support\Webhooks;

/**
 * The SSRF check behind outbound webhooks (Issue #36): a subscription URL
 * makes the server issue an HTTP request to a value an account holder chose,
 * and portal accounts are cheap (Nostr/LNURL login).
 *
 * Used twice: once at subscription create/update (App\Rules\PublicHttpsUrl),
 * once again right before every delivery attempt (App\Jobs\DeliverWebhookJob)
 * — a hostname that resolved to a public IP when the subscription was
 * approved can be repointed to an internal one afterwards (DNS rebinding),
 * and the second check is what catches that.
 */
class SsrfGuard
{
    /**
     * https scheme, resolvable host, and every resolved address outside the
     * private/reserved ranges (loopback, RFC1918, link-local — including the
     * cloud metadata address 169.254.169.254 — and the other IANA-reserved
     * blocks FILTER_FLAG_NO_RES_RANGE covers).
     *
     * An unresolvable host fails closed: it cannot be proven safe, and a
     * silently-allowed unresolvable host today can resolve to anything by the
     * time the delivery actually runs.
     */
    public static function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        $ips = self::resolve($parts['host']);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every address a host currently resolves to. A literal IP in the URL
     * resolves to itself without a DNS lookup.
     *
     * @return array<int, string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
