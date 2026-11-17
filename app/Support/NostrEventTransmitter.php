<?php

namespace App\Support;

use swentel\nostr\Event\Event;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\RelayResponse\RelayResponse;
use swentel\nostr\RelayResponse\RelayResponseOk;
use swentel\nostr\Request\Request;
use Throwable;

/**
 * Offers one signed event to each relay and reports what EACH relay answered.
 *
 * A container binding, so the commands above it are testable without a websocket; this
 * class is the one piece a test replaces.
 *
 * ## What this replaced (2026-11-17)
 *
 * `transmit()` returned one boolean, true as soon as any frame of any relay had
 * `isSuccess === true`. Two defects in one line:
 *
 *  1. ONE RELAY WAS ENOUGH. The record was marked published and never offered again, so
 *     a relay that had refused it never got it.
 *  2. ANY FRAME COUNTED. swentel sets `RelayResponse::isSuccess` to true for every frame
 *     type except `OK false` — a NOTICE, an AUTH challenge or a CLOSED "accepted" the
 *     event just as well as an `OK true`. And the text of a refusal was thrown away.
 *
 * Measured the same day by anonymous reads: 557 of the 684 upcoming dates the portal had
 * marked published were held by neither configured relay; neither relay ever held more
 * than ten (nos.lol) or eight (relay.damus.io) of the portal's events from one burst.
 *
 * ## The rule now
 *
 * A relay has accepted an event when it sent `["OK", <that event's id>, true, …]`.
 * Nothing else counts. Whether enough relays accepted is the caller's decision
 * ({@see NostrBroadcastResult::reached()} with {@see NostrRelays::quorum()}).
 */
class NostrEventTransmitter
{
    /**
     * Seconds of silence before one relay is abandoned. swentel's default is 60, which
     * with seven relays lets one hanging relay eat a whole scheduler tick.
     */
    public const TIMEOUT_SECONDS = 8;

    /**
     * After this many refusals in a row a relay sits out the rest of the run: a relay
     * that is down, rate-limiting or closed to this key is not asked again every second.
     */
    public const MAX_CONSECUTIVE_REFUSALS = 2;

    /**
     * @var array<string, int> relay => refusals in a row, for the lifetime of this instance (one command run)
     */
    private array $consecutiveRefusals = [];

    /**
     * @param  list<string>  $relayUrls
     */
    public function transmit(Event $event, array $relayUrls): NostrBroadcastResult
    {
        $answers = [];

        foreach (NostrRelays::normaliseList($relayUrls) as $relayUrl) {
            if (($this->consecutiveRefusals[$relayUrl] ?? 0) >= self::MAX_CONSECUTIVE_REFUSALS) {
                $answers[] = NostrRelayAnswer::skipped($relayUrl, 'not offered: relay refused '.self::MAX_CONSECUTIVE_REFUSALS.' events in a row in this run');

                continue;
            }

            $answer = $this->offer($event, $relayUrl);

            $this->consecutiveRefusals[$relayUrl] = $answer->accepted ? 0 : ($this->consecutiveRefusals[$relayUrl] ?? 0) + 1;

            $answers[] = $answer;
        }

        return new NostrBroadcastResult($event->getId(), $answers);
    }

    /**
     * One websocket, one EVENT, one relay. `protected` so a test can take the socket out
     * of the way and still exercise the loop above.
     */
    protected function offer(Event $event, string $relayUrl): NostrRelayAnswer
    {
        try {
            $request = new Request(new Relay($relayUrl), new EventMessage($event));
            $request->setTimeout(self::TIMEOUT_SECONDS);

            $response = $request->send();
        } catch (Throwable $exception) {
            return NostrRelayAnswer::refused($relayUrl, 'transport: '.$exception->getMessage());
        }

        return self::interpret($relayUrl, $event->getId(), $response[array_key_first($response)] ?? []);
    }

    /**
     * Turns swentel's frame list for ONE relay into an answer. Public and static so the
     * rule is testable against the exact frame shapes swentel produces, without a socket.
     *
     * swentel reports an `OK false` and a broken socket the same way: it throws, and
     * `Request::send()` turns the exception into `['ERROR', '', false, <message>]`. For
     * an `OK false` that message is the relay's own refusal text.
     *
     * @param  list<mixed>  $frames
     */
    public static function interpret(string $relayUrl, string $eventId, array $frames): NostrRelayAnswer
    {
        $refusal = null;

        foreach ($frames as $frame) {
            if ($frame instanceof RelayResponseOk && $frame->status === true && $frame->eventId === $eventId) {
                return NostrRelayAnswer::accepted($relayUrl, $frame->message ?? null);
            }

            $refusal ??= match (true) {
                $frame instanceof RelayResponseOk && $frame->status === true => 'OK for another event id: '.$frame->eventId,
                $frame instanceof RelayResponseOk => isset($frame->message) && $frame->message !== '' ? $frame->message : 'OK false without a message',
                $frame instanceof RelayResponse => trim(strtolower($frame->type ?? 'frame').': '.($frame->message ?? '')),
                is_array($frame) => (string) ($frame[3] ?? json_encode($frame)),
                default => 'unexpected frame',
            };
        }

        return NostrRelayAnswer::refused($relayUrl, $refusal ?? 'no OK frame received');
    }
}
