<?php

namespace Tests\Fixtures;

use App\Support\NostrEventTransmitter;
use swentel\nostr\Event\Event;

/**
 * A {@see NostrEventTransmitter} that records instead of opening a websocket.
 *
 * The NIP-52 command tests need more than "was transmit called": issue #104 is about
 * WHAT goes over the wire — which kind, which `d` tag, which `created_at`, which `a`
 * tags — and a Mockery expectation that only counts calls cannot answer any of that.
 * Binding this into the container is also what keeps a test run from ever addressing a
 * real relay.
 */
class RecordingNostrTransmitter extends NostrEventTransmitter
{
    /**
     * Every event handed to transmit(), in order.
     *
     * @var list<Event>
     */
    public array $events = [];

    /**
     * The relay list each of those calls was given, index-aligned with $events.
     *
     * @var list<list<string>>
     */
    public array $relayLists = [];

    /**
     * What transmit() reports. `false` stands for "no relay accepted it".
     */
    public bool $accepts = true;

    /**
     * Kinds that are rejected even while $accepts is true, so a test can fail exactly
     * one half of a run — the calendar refresh, say, while the event itself succeeds.
     *
     * @var list<int>
     */
    public array $rejectedKinds = [];

    /**
     * @param  list<string>  $relayUrls
     */
    public function transmit(Event $event, array $relayUrls): bool
    {
        $this->events[] = $event;
        $this->relayLists[] = $relayUrls;

        if (in_array($event->getKind(), $this->rejectedKinds, true)) {
            return false;
        }

        return $this->accepts;
    }

    /**
     * The recorded events of one kind.
     *
     * @return list<Event>
     */
    public function ofKind(int $kind): array
    {
        return array_values(array_filter($this->events, fn (Event $event): bool => $event->getKind() === $kind));
    }

    /**
     * The value of the first tag with the given name, or null when there is none.
     */
    public static function tagValue(Event $event, string $name): ?string
    {
        $tags = $event->getTag($name);

        return $tags === [] ? null : $tags[0][1];
    }

    /**
     * The values of every tag with the given name, in the order they were added.
     *
     * @return list<string>
     */
    public static function tagValues(Event $event, string $name): array
    {
        return array_map(fn (array $tag): string => $tag[1], $event->getTag($name));
    }
}
