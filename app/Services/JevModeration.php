<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Jev content pre-screening (shadow mode) for user-generated content.
 *
 * Product pilot per plan 2026-09-19T1335 (P5 conditional go, reviewer
 * conditions): smallest possible scope, hard PII rule, per-call logging,
 * fail-open. This class NEVER blocks content — it screens and logs, so
 * thresholds can be calibrated on real traffic before any enforcement
 * is even discussed.
 *
 * Provider: classifier.dev (keyless zero-shot classification, HTTP POST).
 * The service is called from the meetup event observer on create.
 *
 * PII rule (hard): the screened payload carries title and description
 * only — no user names, pubkeys, or other identity data. Community
 * content text is the input, nothing else.
 */
class JevModeration
{
    /**
     * Screen a meetup event's public text and log the verdict.
     *
     * @return array{verdict: string, confidence: float|null, ms: int, ok: bool}
     *         ok=false means the screen itself failed (fail-open: treat as
     *         unscreened, never as flagged).
     */
    public function screen(string $title, string $description): array
    {
        $started = microtime(true);

        if (! config('services.jev.enabled')) {
            return ['verdict' => 'disabled', 'confidence' => null, 'ms' => 0, 'ok' => false];
        }

        $text = mb_substr(trim($title."\n".$description), 0, 8000);

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'einundzwanzig-portal-jev/1.0'])
                ->post(config('services.jev.url', 'https://classifier.dev'), [
                    'input' => $text,
                    'labels' => [
                        'ok',
                        'spam_werbung',
                        'beleidigung_hass',
                        'link_farming',
                        'sonstiges_problem',
                    ],
                    'instructions' => 'Community event title and description. '.
                        'spam_werbung = unsolicited advertising; beleidigung_hass = abusive or hateful; '.
                        'link_farming = exists mainly to place links; sonstiges_problem = harmful but none of the above.',
                ]);

            $result = $response->json('results.0') ?? [];
            $verdict = (string) ($result['label'] ?? 'unbekannt');
            $confidence = isset($result['confidence']) ? (float) $result['confidence'] : null;

            $outcome = [
                'verdict' => $verdict,
                'confidence' => $confidence,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'ok' => true,
            ];
        } catch (\Throwable $e) {
            // Fail-open: a screening outage must never affect event creation.
            $outcome = ['verdict' => 'jev_ausfall', 'confidence' => null,
                'ms' => (int) round((microtime(true) - $started) * 1000), 'ok' => false];
        }

        Log::info('meetup-event-screen', $outcome);

        return $outcome;
    }
}
