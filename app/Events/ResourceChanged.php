<?php

namespace App\Events;

use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Eine Aenderung an einer oeffentlichen API-Ressource, unterwegs zu Reverb (Issue #29).
 *
 * DIESE KLASSE TRAEGT EIN ARRAY UND NIEMALS EIN MODEL. Das ist die eine Eigenschaft,
 * die sie nie verlieren darf, und der Grund dafuer steht in Laravels eigenem Code:
 * {@see BroadcastEvent} setzt `deleteWhenMissingModels = true`
 * (Zeile 66). Traegt ein Event ein Eloquent-Model in die Queue, versucht
 * `SerializesModels` es beim Ausfuehren nachzuladen — und findet es bei einem `deleted`
 * nie wieder, weil kein Model dieses Projekts SoftDeletes nutzt. Der Job wird dann
 * STILL verworfen: kein Fehler, kein `failed_jobs`-Eintrag, kein Log. Ein Konsument
 * erfuehre von Loeschungen nie etwas, und niemand saehe, dass etwas fehlt.
 *
 * Derselbe Mechanismus faelschte bei `updated` das Payload: das nachgeladene Model
 * traegt den Stand zur SENDEzeit, nicht den zur Aenderungszeit — bei mehreren
 * parallelen Horizon-Prozessen also in beliebiger Reihenfolge.
 *
 * Deshalb: kein `SerializesModels`, keine Model-Property, keine Resource-Aufloesung im
 * Job. Das Payload ist fertig, wenn es hier ankommt; {@see ChangeRecorder} hat es
 * synchron im Request gebaut. `tests/Feature/Broadcasting/ResourceChangedTest.php`
 * haelt das per Reflection fest.
 */
class ResourceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload  Der fertige Umschlag aus
     *                                         {@see ChangeRecorder::broadcastPayload()} — schon auf 10 KB geprueft.
     */
    public function __construct(public readonly array $payload) {}

    /**
     * Der Event-Name auf dem Draht, etwa `meetup-event.created`.
     *
     * OHNE FUEHRENDEN PUNKT, und das ist der Punkt. Was diese Methode zurueckgibt,
     * geht WOERTLICH ueber die Leitung: {@see BroadcastEvent::handle()}
     * nimmt `enum_value($this->event->broadcastAs())` als Event-Namen, ohne irgendetwas
     * daran zu aendern. Der fuehrende Punkt, den man aus Laravel-Beispielen kennt, ist
     * CLIENT-Syntax: `Echo.channel(…).listen('.meetup-event.created')` sagt Echo „stell
     * mir keinen App-Namespace voran"; Echo entfernt ihn und abonniert
     * `meetup-event.created`. Er reist nie mit.
     *
     * Stuende der Punkt hier, hiesse das Ereignis auf dem Draht buchstaeblich
     * `.meetup-event.created` — und ein Echo-Konsument mit der naheliegenden
     * Schreibweise abonnierte `meetup-event.created` und hoerte fuer immer nichts.
     * Ein oeffentlicher Pusher-Kanal hat keinen Rueckkanal: ein Abo auf einen Namen,
     * den niemand sendet, ist erfolgreich und still. Genau die Fehlerklasse, vor der
     * /docs/websockets warnt — sie darf nicht aus diesem Repo kommen.
     */
    public function broadcastAs(): string
    {
        return sprintf('%s.%s', $this->payload['resource'], $this->payload['action']);
    }

    /**
     * Genau zwei oeffentliche Kanaele — und `meetup-events` traegt nur Termine.
     *
     * `portal` ist der Firehose: ein Abo, ein Handler, alle sechs Ressourcen. Das ist
     * der "clear caches instantly"-Fall aus Issue #29.
     *
     * `meetup-events` ist der einzige gegenstandsbezogene Kanal, weil Meetup-Termine
     * das einzige sind, was im Issue woertlich benannt wurde. Ein Kanal, der so heisst
     * und dann auch Kurse und Staedte truege, waere schlimmer als kein Kanal: sein
     * Name sagt etwas zu, was er nicht haelt, der Konsument filtert entweder nach oder
     * verarbeitet Fremdes — und merkt den Unterschied nie, weil ein oeffentlicher
     * Pusher-Kanal keinen Rueckkanal hat. Die Zusammensetzung wird deshalb hier
     * entschieden, an der Ressource im Payload, und nicht beim Dispatch: der Recorder
     * kennt Kanaele nicht, und ein zweiter Ort mit derselben Regel waere ein Ort, an
     * dem sie eines Tages abweicht.
     *
     * Weitere Kanalfamilien (Geo, Entity, RSVP) sind P7 und gehen erst raus, wenn ein
     * Konsument sie mit Namen anfragt. Ein veroeffentlichter Kanal ist ein Vertrag,
     * dessen Bruch beim Abonnenten lautlos ist.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('portal')];

        if ($this->payload['resource'] === 'meetup-event') {
            $channels[] = new Channel('meetup-events');
        }

        return $channels;
    }

    /**
     * Das Payload, unveraendert.
     *
     * Kein zweites Format: was hier rausgeht, ist byte-gleich zu dem, was
     * {@see ChangeRecorder::broadcastPayload()} aus der `api_changes`-Zeile macht.
     * Haette der Kanal eine eigene Gestalt, muesste ein Konsument nach einem
     * Verbindungsabriss zwei Parser pflegen — und der Resync ueber `/api/changes` waere
     * kein Resync, sondern eine Uebersetzung.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
