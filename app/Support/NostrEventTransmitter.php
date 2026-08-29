<?php

namespace App\Support;

use swentel\nostr\Event\Event;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Request\Request;
use Throwable;

/**
 * Kapselt den echten Websocket-Versand an Relays (swentel/nostr-php).
 *
 * Eigene Klasse statt Inline-Code im Command: die Signatur- und Tag-Logik in
 * {@see NostrCalendarEventFactory} laesst sich ohne Netzwerk testen, aber der
 * Versand selbst braucht eine echte Websocket-Verbindung. Dieser Ausschnitt ist
 * deshalb der einzige Ort, den ein Test faken muss (Container-Bindung), statt
 * echte Relays anzusprechen.
 */
class NostrEventTransmitter
{
    /**
     * Sendet ein signiertes Event an jedes Relay und meldet Erfolg, sobald
     * mindestens eins es angenommen hat ("OK", true).
     *
     * @param  list<string>  $relayUrls
     */
    public function transmit(Event $event, array $relayUrls): bool
    {
        $eventMessage = new EventMessage($event);
        $accepted = false;

        foreach ($relayUrls as $relayUrl) {
            try {
                $relay = new Relay($relayUrl);
                $request = new Request($relay, $eventMessage);
                $response = $request->send();

                foreach ($response as $relayResponses) {
                    foreach ($relayResponses as $relayResponse) {
                        if (($relayResponse->isSuccess ?? false) === true) {
                            $accepted = true;
                        }
                    }
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $accepted;
    }
}
