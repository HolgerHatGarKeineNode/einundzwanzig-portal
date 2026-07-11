<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

/**
 * Downloads a Nostr profile picture (kind-0 "picture") to the public disk and
 * returns the stored path. Shared by the profile-sync job and the account-merge
 * wizard so the SSRF protection lives in exactly one place.
 *
 * The URL is fully attacker-controlled (it comes from a signed kind-0 event), so
 * the download is hardened against SSRF: the host is resolved once, every
 * resolved IP must be public, the connection is pinned to that validated IP (no
 * DNS-rebinding TOCTOU), redirects are not followed (no redirect-to-internal),
 * and only a real image Content-Type is accepted (no attacker-chosen .php/.html
 * landing in the web root).
 */
final class NostrProfilePhoto
{
    /** Content-Type => stored file extension. The extension is NEVER taken from the URL. */
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    /**
     * Download the image at $url and store it, returning the storage path, or
     * null if the URL is disallowed, resolves to a private address, redirects,
     * or is not a real image.
     */
    public static function store(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return self::refuse($url, 'malformed url');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return self::refuse($url, 'disallowed scheme');
        }

        $ip = self::resolveToPublicIp($parts['host']);
        if ($ip === null) {
            return self::refuse($url, 'host does not resolve to a public IP');
        }

        try {
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

            $response = Http::timeout(10)
                ->withOptions([
                    // No redirect-following: an initially-public URL cannot bounce
                    // to an internal host. A 3xx is simply treated as unsuccessful.
                    'allow_redirects' => false,
                    // Pin the connection to the IP we validated, so a low-TTL
                    // rebinding domain cannot re-resolve to an internal address
                    // between the check and the fetch.
                    'curl' => [CURLOPT_RESOLVE => [$parts['host'].':'.$port.':'.$ip]],
                ])
                ->get($url);

            if (! $response->successful()) {
                return self::refuse($url, 'non-2xx response '.$response->status());
            }

            $ext = self::IMAGE_TYPES[self::normalizeContentType($response->header('Content-Type'))] ?? null;
            if ($ext === null) {
                return self::refuse($url, 'not an image content-type');
            }

            $path = 'profile-photos/'.Uuid::uuid1().'.'.$ext;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            Log::error('Failed to save Nostr profile photo', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private static function refuse(string $url, string $reason): null
    {
        Log::warning('Refused Nostr profile photo download', ['url' => $url, 'reason' => $reason]);

        return null;
    }

    /**
     * Resolve a host to a single public IP, requiring EVERY resolved address to
     * be public (a mixed public/private result is rejected). Returns the IP to
     * pin the connection to, or null.
     */
    private static function resolveToPublicIp(string $host): ?string
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $ips[0];
    }

    private static function normalizeContentType(?string $contentType): string
    {
        if ($contentType === null) {
            return '';
        }

        return strtolower(trim(explode(';', $contentType)[0]));
    }
}
