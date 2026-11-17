<?php

namespace App\Support;

use swentel\nostr\Event\Event;

/**
 * The answers of every relay one event was offered to — the replacement for the single
 * boolean {@see NostrEventTransmitter::transmit()} returned until 2026-11-17.
 *
 * That boolean was true as soon as ANY relay produced ANY frame that was not `OK false`.
 * Measured on 2026-11-17 against the public relays: of 684 upcoming dates the portal had
 * marked as published, 557 were held by neither configured relay.
 */
final readonly class NostrBroadcastResult
{
    /**
     * @param  list<NostrRelayAnswer>  $answers  in the order the relays were asked
     */
    public function __construct(
        public string $eventId,
        public array $answers,
    ) {}

    /**
     * Every relay accepted. For tests and fakes; the real transmitter builds answers
     * from frames.
     *
     * @param  list<string>  $relays
     */
    public static function acceptedByAll(Event $event, array $relays): self
    {
        return new self($event->getId(), array_map(
            fn (string $relay): NostrRelayAnswer => NostrRelayAnswer::accepted($relay),
            array_values($relays),
        ));
    }

    /**
     * Every relay refused with the same message. For tests and fakes.
     *
     * @param  list<string>  $relays
     */
    public static function refusedByAll(Event $event, array $relays, string $message = 'blocked: refused'): self
    {
        return new self($event->getId(), array_map(
            fn (string $relay): NostrRelayAnswer => NostrRelayAnswer::refused($relay, $message),
            array_values($relays),
        ));
    }

    /**
     * @return list<string>
     */
    public function acceptedBy(): array
    {
        return array_values(array_map(
            fn (NostrRelayAnswer $answer): string => $answer->relay,
            array_filter($this->answers, fn (NostrRelayAnswer $answer): bool => $answer->accepted),
        ));
    }

    /**
     * @return list<NostrRelayAnswer>
     */
    public function refusals(): array
    {
        return array_values(array_filter($this->answers, fn (NostrRelayAnswer $answer): bool => ! $answer->accepted));
    }

    public function acceptedCount(): int
    {
        return count($this->acceptedBy());
    }

    /**
     * The rule for "done": at least $quorum relays said `OK true` to this very event.
     */
    public function reached(int $quorum): bool
    {
        return $this->acceptedCount() >= max(1, $quorum);
    }

    public function answerOf(string $relay): ?NostrRelayAnswer
    {
        foreach ($this->answers as $answer) {
            if ($answer->relay === $relay) {
                return $answer;
            }
        }

        return null;
    }

    /**
     * One line for console and log: `2/3 accepted; wss://x: rate-limited: slow down`.
     */
    public function summary(): string
    {
        $parts = [sprintf('%d/%d accepted', $this->acceptedCount(), count($this->answers))];

        foreach ($this->refusals() as $answer) {
            $parts[] = sprintf('%s: %s', $answer->relay, $answer->message ?? 'no answer');
        }

        return implode('; ', $parts);
    }
}
