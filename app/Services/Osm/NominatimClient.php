<?php

namespace App\Services\Osm;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the Nominatim geocoding API.
 *
 * The usage policy shapes this class more than the feature does
 * (https://operations.osmfoundation.org/policies/nominatim/, read 2026-08-17):
 *
 *   - an absolute maximum of 1 request per second, and only 4 per MINUTE for bulk
 *     geocoding that runs regularly or for longer than a day
 *   - results MUST be cached client-side
 *   - a meaningful User-Agent is required; the stock header of an HTTP library is
 *     explicitly rejected
 *   - bulk geocoding is "not encouraged" at all
 *
 * Hence: every call goes through the cache first, a minimum interval is enforced
 * between real requests, and the caller can widen that interval for bulk work.
 *
 * Fail-soft throughout. A geocoding hiccup must never take down an event form — the
 * free-text location field is always there as the fallback.
 */
class NominatimClient
{
    /** Milliseconds since the epoch of the last outgoing request, per process. */
    private static ?float $lastRequestAt = null;

    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $userAgent = null,
        private readonly int $minIntervalMs = 1100,
    ) {}

    /**
     * A bulk-safe instance: 4 requests per minute, as the policy demands for scripts.
     */
    public static function forBulk(): self
    {
        return new self(minIntervalMs: 15_000);
    }

    /**
     * Search for a place. Returns an empty collection on any failure.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, ?string $countryCode = null, int $limit = 5): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return collect();
        }

        $params = array_filter([
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            // Bringt wikidata und wikipedia mit ("wikidata": "Q84", "wikipedia": "en:London").
            // Derselbe Request, ein Parameter mehr — keine zusaetzliche Last.
            'extratags' => 1,
            'limit' => $limit,
            'countrycodes' => $countryCode ? mb_strtolower($countryCode) : null,
        ], fn ($value): bool => $value !== null);

        $cacheKey = 'osm:search:'.md5(json_encode($params));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($params): Collection {
            $response = $this->get('/search', $params);

            if ($response === null) {
                return collect();
            }

            return collect($response)
                ->map(fn (array $row): array => $this->normalise($row))
                ->filter()
                ->values();
        });
    }

    /**
     * Resolve a known osm_type/osm_id pair back to its current name and address.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(string $osmType, int $osmId): ?array
    {
        $prefix = match (mb_strtolower($osmType)) {
            'node' => 'N',
            'way' => 'W',
            'relation' => 'R',
            default => null,
        };

        if ($prefix === null) {
            return null;
        }

        /*
         * Der Schluessel traegt eine Version, weil er — anders als in search() — die
         * Parameter nicht enthaelt. Ohne das v2 lieferte der 30-Tage-Cache nach dem
         * Einbau von extratags noch einen Monat lang die alten Eintraege ohne wikidata
         * und wikipedia, und der neue Code saehe aus, als funktioniere er nicht.
         * Kommt ein weiterer Parameter dazu, wird die Zahl wieder erhoeht.
         */
        $cacheKey = "osm:lookup:v2:{$prefix}{$osmId}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($prefix, $osmId): ?array {
            $response = $this->get('/lookup', [
                'osm_ids' => $prefix.$osmId,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'extratags' => 1,
            ]);

            if (! is_array($response) || $response === []) {
                return null;
            }

            return $this->normalise($response[0]);
        });
    }

    /**
     * Shape a Nominatim row into the fields the events table stores.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalise(array $row): ?array
    {
        if (! isset($row['osm_type'], $row['osm_id'])) {
            return null;
        }

        $display = (string) ($row['display_name'] ?? '');
        $name = (string) ($row['name'] ?? '');

        if ($name === '') {
            // Nominatim omits `name` for plain addresses; the first segment of
            // display_name is then the closest thing to one.
            $name = trim(explode(',', $display)[0] ?? '');
        }

        // extratags fehlt, wenn der Aufruf ohne den Parameter lief oder das Objekt keine
        // Zusatz-Tags traegt — beides ist normal, beides ergibt hier null.
        $extra = is_array($row['extratags'] ?? null) ? $row['extratags'] : [];

        return [
            'osm_type' => (string) $row['osm_type'],
            'osm_id' => (int) $row['osm_id'],
            'osm_name' => $name !== '' ? mb_substr($name, 0, 255) : null,
            'osm_address' => $display !== '' ? mb_substr($display, 0, 255) : null,
            'osm_lat' => isset($row['lat']) ? (float) $row['lat'] : null,
            'osm_lon' => isset($row['lon']) ? (float) $row['lon'] : null,
            'importance' => isset($row['importance']) ? (float) $row['importance'] : null,
            'category' => $row['category'] ?? $row['class'] ?? null,
            // Wikidata-Q-ID, z. B. "Q84". Nominatim liefert sie nur mit extratags=1.
            'wikidata' => $this->tag($extra, 'wikidata', 32),
            // OSM-Slug der Form "de:Koeln" — kein fertiger Link, siehe Region-Accessoren.
            'wikipedia' => $this->tag($extra, 'wikipedia', 255),
            // Nur bei Orten mit population-Tag gesetzt; Staedte und Laender tragen ihn oft.
            'population' => isset($extra['population']) && is_numeric($extra['population'])
                ? (int) $extra['population']
                : null,
        ];
    }

    /**
     * Einen Zusatz-Tag lesen: getrimmt, laengenbegrenzt, leer wird zu null.
     *
     * @param  array<string, mixed>  $extra
     */
    private function tag(array $extra, string $key, int $maxLength): ?string
    {
        $value = trim((string) ($extra[$key] ?? ''));

        return $value !== '' ? mb_substr($value, 0, $maxLength) : null;
    }

    /**
     * One throttled, identified request. Returns null on any failure.
     *
     * @param  array<string, mixed>  $params
     */
    private function get(string $path, array $params): ?array
    {
        $this->throttle();

        try {
            $response = Http::withHeaders(['User-Agent' => $this->agent()])
                ->timeout(10)
                ->get($this->base().$path, $params);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Hold back until the minimum interval since the last request has elapsed.
     */
    private function throttle(): void
    {
        $now = microtime(true) * 1000;

        if (self::$lastRequestAt !== null) {
            $waited = $now - self::$lastRequestAt;

            if ($waited < $this->minIntervalMs) {
                usleep((int) (($this->minIntervalMs - $waited) * 1000));
            }
        }

        self::$lastRequestAt = microtime(true) * 1000;
    }

    private function base(): string
    {
        return rtrim($this->baseUrl ?? config('services.nominatim.url', 'https://nominatim.openstreetmap.org'), '/');
    }

    /**
     * A stock library User-Agent is explicitly rejected by the policy, so identify the
     * application and give them a way to reach us.
     */
    private function agent(): string
    {
        return $this->userAgent
            ?? config('services.nominatim.user_agent')
            ?? 'einundzwanzig-portal/1.0 (+'.config('app.url').')';
    }

    /**
     * Reset the throttle. For tests only — production has one long-lived process state.
     */
    public static function resetThrottle(): void
    {
        self::$lastRequestAt = null;
    }
}
