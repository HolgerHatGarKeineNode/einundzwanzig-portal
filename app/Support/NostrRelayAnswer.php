<?php

namespace App\Support;

/**
 * What ONE relay said to ONE event.
 *
 * `accepted` is true for exactly one thing: an `OK` frame with status `true` that names
 * the id of the event that was sent. Everything else — `OK false`, a NOTICE, an AUTH
 * challenge, a closed socket, silence — is a refusal, and `message` says which.
 */
final readonly class NostrRelayAnswer
{
    public const MAX_MESSAGE_LENGTH = 500;

    private function __construct(
        public string $relay,
        public bool $accepted,
        public ?string $message,
        public bool $offered,
    ) {}

    public static function accepted(string $relay, ?string $message = null): self
    {
        return new self($relay, true, self::clip($message), true);
    }

    /**
     * The event was offered and not accepted: the relay's own refusal text, or what the
     * transport said when no answer came.
     */
    public static function refused(string $relay, string $message): self
    {
        return new self($relay, false, self::clip($message), true);
    }

    /**
     * The event was NOT offered to this relay — it had just failed twice in a row in the
     * same run and sits the rest of it out. Not an answer of the relay, so the ledger
     * keeps what the relay last really said.
     */
    public static function skipped(string $relay, string $reason): self
    {
        return new self($relay, false, self::clip($reason), false);
    }

    /**
     * A relay's text is foreign input that ends up in a log line and a database column.
     * Control characters go, the length is bounded.
     */
    private static function clip(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', mb_scrub($message, 'UTF-8')) ?? '';

        return mb_substr(trim($message), 0, self::MAX_MESSAGE_LENGTH, 'UTF-8');
    }
}
