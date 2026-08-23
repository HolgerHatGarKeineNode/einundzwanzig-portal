<?php

use App\Support\Broadcasting\ChangeRecorder;

/*
 * Die Konsumenten-Doku unter /docs/websockets (Plan-Phase P6).
 *
 * Der Zweck dieser Tests ist nicht "die Seite rendert" — sie ist eine Blade-Datei, das
 * tut sie fast immer. Geprueft wird, was an ihr ein Aussenvertrag ist: dass sie ohne
 * Anmeldung erreichbar ist, dass sie genau die zwei Kanaele nennt, die es gibt, und
 * dass die Verbindungsangaben aus der Konfiguration kommen. Der letzte Punkt ist der
 * teuerste Fehler, den diese Seite machen koennte: ein fest verdrahteter App-Key waere
 * ab dem Tag falsch, an dem P5 den echten setzt, und niemand saehe es.
 */

it('serves the websocket documentation to guests', function () {
    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        ->assertSee('Realtime change feed');
});

it('names both channels and only those two', function () {
    $response = $this->get(route('docs.websockets'))->assertSuccessful();

    $response->assertSee('portal');
    $response->assertSee('meetup-events');

    /*
     * Die Kanalfamilien aus P7 sind entworfen, aber nicht gebaut. Stuenden sie hier,
     * abonnierte ein Konsument einen Kanal, den es nicht gibt — erfolgreich und fuer
     * immer still, weil ein oeffentlicher Pusher-Kanal keinen Rueckkanal hat. Genau
     * deshalb ist eine zu grosszuegige Doku hier schlimmer als gar keine.
     */
    foreach (['meetups.country.', 'meetup-events.country.', 'meetups.city.', 'meetup-events.rsvp.', 'cities.country.'] as $unbuiltChannel) {
        $response->assertDontSee($unbuiltChannel);
    }
});

it('links to the resync endpoint and to the api reference', function () {
    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        ->assertSee(route('api.changes.index'))
        ->assertSee(route('scramble.docs.ui'));
});

it('spells out the payload contract, the pitfalls and the accepted gaps', function () {
    $response = $this->get(route('docs.websockets'))->assertSuccessful();

    // Event-Namen OHNE fuehrenden Punkt, so wie sie ueber die Leitung gehen — und der
    // Punkt, der nur Echo-Syntax ist, ausdruecklich erklaert. Steht hier der Punkt im
    // Draht-Namen, abonniert ein Echo-Konsument mit der naheliegenden Schreibweise
    // einen Namen, den niemand sendet: erfolgreich und fuer immer still.
    $response->assertSee('meetup-event.created');
    $response->assertSee('meetup-event.deleted');
    // Die Liste selbst traegt keinen Punkt — und zwar nachweislich nicht.
    $response->assertSee('<li>meetup-event.created</li>', false);
    $response->assertDontSee('<li>.meetup-event.created</li>', false);
    $response->assertSee('The leading dot: where it belongs, and where it does not');
    $response->assertSee("bind('meetup-event.created', handler)", false);
    $response->assertSee("listen('.meetup-event.created', handler)", false);

    // Der deleted-Fall: data null plus previous — der einzige Weg, von einer Loeschung
    // zu erfahren, weil kein Model SoftDeletes nutzt.
    $response->assertSee('&quot;previous&quot;', false);

    // Der Umschlag von /api/changes.
    foreach (['next_since', 'has_more', 'cursor_expired'] as $field) {
        $response->assertSee($field);
    }

    // Die vier Stolperfallen.
    $response->assertSee('4009');
    $response->assertSee('links.self');
    $response->assertSee('truncated');

    // Die 10-KB-Grenze, aus dem Recorder gelesen statt hingeschrieben.
    $response->assertSee(number_format(ChangeRecorder::MAX_BROADCAST_BYTES, 0, '.', ' '));

    // Der Deploy-Hinweis.
    $response->assertSee('Every deploy drops every connection');
});

it('shows a placeholder instead of an invented value while reverb is unpublished', function () {
    config([
        'broadcasting.connections.reverb.key' => null,
        'reverb.servers.reverb.path' => '',
        'broadcasting.connections.reverb.options.path' => '',
    ]);

    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        ->assertSee('{REVERB_APP_KEY}')
        ->assertSee('The socket is not published in this environment yet.')
        /*
         * Fuer den PFAD gibt es bewusst keinen Platzhalter mehr: leer ist seit P5 der
         * Normalfall (eigene Subdomain, Handshake unter dem Standardpfad /app), und
         * ein Platzhalter an dieser Stelle stuende in der URL und liesse eine
         * vollstaendige Adresse wie eine unfertige aussehen.
         */
        ->assertDontSee('{REVERB_SERVER_PATH}');
});

it('reads the connection details from the configuration, not from the page', function () {
    /*
     * Der eigentliche Beleg: Config aendern, Seite neu laden, neuer Wert steht da.
     * Ohne diesen Test waere "kommt aus der Config" eine Behauptung ueber Code, den
     * niemand nachfaehrt, sobald P5 die Produktionswerte setzt.
     */
    config([
        'app.url' => 'https://portal.example.test',
        'einundzwanzig.realtime.public_host' => 'ws.portal.example.test',
        'broadcasting.connections.reverb.key' => 'schluessel-aus-der-config',
        'reverb.servers.reverb.path' => 'reverb-pfad-aus-der-config',
    ]);

    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        ->assertSee('schluessel-aus-der-config')
        ->assertSee('/reverb-pfad-aus-der-config')
        ->assertSee('wss://ws.portal.example.test/reverb-pfad-aus-der-config/app/schluessel-aus-der-config')
        ->assertDontSee('{REVERB_APP_KEY}')
        ->assertDontSee('{REVERB_SERVER_PATH}');
});

it('sends consumers to the configured public host, not to the portal domain', function () {
    /*
     * Der Befund aus P5: Reverb haengt NICHT als Pfad-Proxy unter der Portal-Domain,
     * sondern auf einer eigenen Subdomain mit eigenem Zertifikat. Wer hier `app.url`
     * annimmt, schickt jeden Konsumenten an einen Host, auf dem kein Socket steht —
     * und der Fehler faellt erst beim Verbinden auf, nicht beim Lesen.
     */
    config([
        'app.url' => 'https://portal.example.test',
        'einundzwanzig.realtime.public_host' => 'ws.portal.example.test',
        'reverb.servers.reverb.path' => '',
        'broadcasting.connections.reverb.options.path' => '',
        'broadcasting.connections.reverb.key' => 'ein-key',
    ]);

    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        ->assertSee('wss://ws.portal.example.test/app/ein-key')
        // Die Portal-Domain bleibt der REST-Host — aber nicht der Socket-Host.
        ->assertDontSee('wss://portal.example.test');
});

it('falls back to the app url host when no public host is configured', function () {
    /*
     * Lokal (`composer run dev`) faellt beides zusammen, und der Rueckfall ist das,
     * was diese Seite auf einer Entwicklermaschine ueberhaupt brauchbar haelt.
     */
    config([
        'app.url' => 'http://localhost:8000',
        'einundzwanzig.realtime.public_host' => null,
        'reverb.servers.reverb.path' => '',
        'broadcasting.connections.reverb.options.path' => '',
        'broadcasting.connections.reverb.key' => 'lokaler-key',
    ]);

    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        // http → ws, und der Port aus app.url bleibt stehen, weil auch der Host von
        // dort kommt.
        ->assertSee('ws://localhost:8000/app/lokaler-key');
});

it('builds a url without an empty path segment when there is no prefix', function () {
    /*
     * Der Fehler, den diese Zusicherung ausschliesst, ist ein doppelter Schraegstrich
     * oder ein stehengebliebener Platzhalter mitten in einer Adresse, die man kopieren
     * soll. Beides verbindet nicht, und beides sieht auf den ersten Blick richtig aus.
     */
    config([
        'app.url' => 'https://portal.example.test',
        'einundzwanzig.realtime.public_host' => 'ws.portal.example.test',
        'reverb.servers.reverb.path' => '',
        'broadcasting.connections.reverb.options.path' => '',
        'broadcasting.connections.reverb.key' => 'ein-key',
    ]);

    $response = $this->get(route('docs.websockets'))->assertSuccessful();

    $response->assertSee('wss://ws.portal.example.test/app/ein-key');
    $response->assertDontSee('ws.portal.example.test//app');
    $response->assertDontSee('{REVERB_SERVER_PATH}');
    // Ohne Praefix gehoert auch keine wsPath-Zeile ins TypeScript-Beispiel: der
    // pusher-js-Default ist der leere String, die Zeile waere eine Einladung, sie
    // spaeter falsch zu fuellen.
    $response->assertDontSee('wsPath: WS_PATH', false);
    $response->assertSee('— none —');
});

it('falls back to the broadcasting path when the reverb server path is unset', function () {
    /*
     * Beide Schluessel lesen dieselbe Env-Variable, aber nur einer von beiden ist auf
     * einem Rechner ohne publizierte config/reverb.php gesetzt. Faellt der Fallback
     * aus, zeigt die Seite den Platzhalter, obwohl der Wert bekannt ist.
     */
    config([
        'reverb.servers.reverb.path' => '',
        'broadcasting.connections.reverb.options.path' => 'nur-im-broadcasting',
        'broadcasting.connections.reverb.key' => 'irgendein-key',
    ]);

    $this->get(route('docs.websockets'))
        ->assertSuccessful()
        ->assertSee('/nur-im-broadcasting');
});

it('exposes the websockets tag in the openapi document', function () {
    /*
     * Der sichtbare Reiter in der Scalar-UI. Geprueft wird das Dokument, nicht die UI:
     * der Tag muss NACH Scrambles eigenem AddDocumentTags angehaengt werden, das
     * `$document->tags` komplett neu setzt — ein zu frueh eingehaengter Transformer
     * verschwaende spurlos.
     */
    $document = $this->get(route('scramble.docs.document'))
        ->assertSuccessful()
        ->json();

    $tags = collect($document['tags'] ?? []);

    expect($tags->pluck('name'))->toContain('WebSockets')
        ->and($tags->firstWhere('name', 'WebSockets')['description'])->toContain('/docs/websockets');
});

it('links from the api reference to the websocket documentation', function () {
    /*
     * Die Pflicht aus der DoD: /docs/api zeigt sichtbar hierher. Der Tag oben ist die
     * Kuer und kann an Scalars Darstellung scheitern; die Einleitung wird immer
     * gerendert.
     */
    $document = $this->get(route('scramble.docs.document'))
        ->assertSuccessful()
        ->json();

    expect($document['info']['description'])
        ->toContain('## Realtime updates')
        ->toContain('/docs/websockets');
});
