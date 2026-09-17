<?php

namespace App\Support;

use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\RelayResponse\RelayResponse;
use swentel\nostr\RelayResponse\RelayResponseClosed;
use swentel\nostr\RelayResponse\RelayResponseEose;
use swentel\nostr\RelayResponse\RelayResponseEvent;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;
use Throwable;

/**
 * Reads stored events from ONE relay through swentel/nostr-php, and says honestly
 * whether the answer is complete.
 *
 * The read-side counterpart of {@see NostrEventTransmitter}, and a container binding for
 * the same reason: the command above it is testable without a socket, and this class is
 * the one piece a test replaces.
 *
 * ## The three swentel behaviours this class is built around (plan risk R11)
 *
 * 1. `Request` keeps ONE response list for all relays of a RelaySet. A Request here is
 *    therefore always built for a single relay, never for a set.
 * 2. Its receive loop `break`s on the first EOSE of ANY subscription, on a CLOSED, and
 *    on a NOTICE starting with "ERROR:" — and returns what it collected so far without
 *    saying why it stopped. Only an EOSE carrying THIS read's own subscription id, and
 *    not followed by a CLOSED for it, makes the answer complete.
 * 3. A relay that demands NIP-42 AUTH for reading triggers swentel's built-in AUTH,
 *    which signs with the constant key 0x…01 and then keeps exactly ONE further frame.
 *    Measured 2026-09-17 against a local zooid holding two RSVPs: the frames were
 *    AUTH, AUTH, CLOSED(auth-required), OK, EVENT — one of two events, no EOSE. Rule 2
 *    turns that into "unknown" instead of "one RSVP".
 *
 * ## Filters per REQ
 *
 * Measured 2026-09-17 with 1/12/40/100/250 filters in one REQ: nos.lol answered EOSE up
 * to 100 and `CLOSED … ERROR: bad req: arr too big` at 250; relay.damus.io answered 40
 * with EOSE and 503 on other attempts. {@see self::MAX_FILTERS_PER_REQUEST} is therefore
 * 40 and not 100: 100 is covered by ONE of the two configured relays, 40 by both, and a
 * cap that only one relay is known to accept is a cap for that relay. At 20 coordinates
 * per filter it still carries 800 events in one REQ, so the normal case stays one
 * request per relay.
 */
class NostrRelayReader
{
    public const MAX_FILTERS_PER_REQUEST = 40;

    /**
     * Seconds of SILENCE before a read is abandoned. swentel applies it per receive()
     * call, so a relay that keeps sending frames is not cut off by it; the `limit` of
     * every filter is what bounds such a read.
     */
    public const TIMEOUT_SECONDS = 15;

    /**
     * @param  list<array<string, mixed>>  $filters  NIP-01 filters with `kinds`, `authors`, `since`, `limit` and `#x` tag keys.
     */
    public function read(string $relayUrl, array $filters): NostrRelayReadResult
    {
        $events = [];

        foreach (array_chunk($filters, self::MAX_FILTERS_PER_REQUEST) as $chunk) {
            $result = $this->readOnce($relayUrl, $chunk);

            if (! $result->complete) {
                return $result;
            }

            array_push($events, ...$result->events);
        }

        return NostrRelayReadResult::complete($events);
    }

    /**
     * One REQ. `protected` so a test can take the socket out of the way and still measure
     * how a read is cut into requests.
     *
     * @param  list<array<string, mixed>>  $filters
     */
    protected function readOnce(string $relayUrl, array $filters): NostrRelayReadResult
    {
        try {
            $subscriptionId = (new Subscription)->getId();
            $message = new RequestMessage($subscriptionId, array_map($this->toSwentelFilter(...), $filters));

            $request = new Request(new Relay($relayUrl), $message);
            $request->setTimeout(self::TIMEOUT_SECONDS);

            $response = $request->send();
        } catch (Throwable $exception) {
            return NostrRelayReadResult::unknown('exception: '.$exception->getMessage());
        }

        return self::interpret($subscriptionId, $response[array_key_first($response)] ?? []);
    }

    /**
     * Turns swentel's frame list into a verdict. Public and static so the rules above
     * are testable against the exact frame shapes swentel produces, without a socket.
     *
     * @param  list<mixed>  $frames
     */
    public static function interpret(string $subscriptionId, array $frames): NostrRelayReadResult
    {
        $events = [];
        $endOfStoredEvents = false;
        $failure = null;
        $transportFailed = false;

        foreach ($frames as $frame) {
            if (! $frame instanceof RelayResponse) {
                // swentel appends ['ERROR', '', false, <message>] when the socket throws.
                $failure = 'transport: '.(is_array($frame) ? (string) ($frame[3] ?? json_encode($frame)) : 'unexpected frame');
                $transportFailed = true;

                continue;
            }

            if ($frame instanceof RelayResponseEvent && $frame->subscriptionId === $subscriptionId) {
                $events[] = get_object_vars($frame->event);
            }

            if ($frame instanceof RelayResponseEose && $frame->subscriptionId === $subscriptionId) {
                $endOfStoredEvents = true;
            }

            if ($frame instanceof RelayResponseClosed && $frame->subscriptionId === $subscriptionId) {
                // A CLOSED voids an EOSE seen before it; an EOSE after it (a REQ re-sent
                // after AUTH) counts again.
                $endOfStoredEvents = false;
                $failure = 'closed: '.$frame->message;
            }
        }

        if (! $endOfStoredEvents || $transportFailed) {
            return NostrRelayReadResult::unknown($failure ?? 'no EOSE for subscription');
        }

        return NostrRelayReadResult::complete($events);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function toSwentelFilter(array $spec): Filter
    {
        $filter = new Filter;

        foreach ($spec as $key => $value) {
            match (true) {
                $key === 'kinds' => $filter->setKinds($value),
                $key === 'authors' => $filter->setAuthors($value),
                $key === 'since' => $filter->setSince($value),
                $key === 'limit' => $filter->setLimit($value),
                str_starts_with($key, '#') => $filter->setTag($key, $value),
                default => throw new \InvalidArgumentException("Unsupported filter key: {$key}"),
            };
        }

        return $filter;
    }
}
