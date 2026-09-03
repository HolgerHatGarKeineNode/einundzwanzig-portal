<?php

/*
 * Die Konsumenten-Doku unter /docs/webhooks — Box 6 der Definition of done von
 * Issue #36, die als einzige nicht erfuellt war (die Seite existierte nicht).
 *
 * Der Zweck dieser Tests ist nicht "die Seite rendert" — sie ist eine Blade-Datei,
 * das tut sie fast immer. Geprueft wird, was an ihr ein Aussenvertrag ist:
 *
 *  - dass sie ohne Anmeldung erreichbar ist (ein Konsument, der noch kein Konto hat,
 *    muss lesen koennen, worauf er sich einlaesst),
 *  - dass der Signatur-Schnipsel wirklich drinsteht, samt der zwei Regeln, ohne die
 *    er nicht funktioniert (Rohkoerper, konstante Zeit),
 *  - und dass jede ZAHL auf der Seite aus der Konfiguration kommt statt aus dem
 *    Fliesstext. Das ist der teuerste Fehler, den diese Seite machen koennte: ein
 *    Retry-Plan, der nicht mehr der gefahrene ist, sieht richtig aus und ist falsch,
 *    und ein Empfaenger richtet seine Alarmierung danach ein.
 */

it('serves the webhook documentation to guests', function () {
    $this->get(route('docs.webhooks'))
        ->assertSuccessful()
        ->assertSee('Outbound webhooks');
});

it('carries every section the issue asks for', function () {
    $response = $this->get(route('docs.webhooks'))->assertSuccessful();

    foreach ([
        // Registrierungs-Walkthrough
        'Registering a subscription',
        '/api/webhook-subscriptions',
        'The secret, once',
        // Header-Tabelle
        'The headers on every delivery',
        // Signatur
        'Verifying the signature in TypeScript',
        // Retry/Abschaltung/Recovery
        'Success, retries and auto-disable',
        'webhook:retry',
        // At-least-once, Dedupe, Luecken-Recovery
        'Delivery semantics',
        'At-least-once',
        'Deduplicate on two keys',
        'Gap recovery',
        // Loesch-Semantik
        'The payload',
        // Stolperfallen und akzeptierte Luecken
        'Six things that go wrong',
        'Gaps, accepted on purpose',
    ] as $section) {
        $response->assertSee($section);
    }
});

it('names all four delivery headers and the signature formula', function () {
    $response = $this->get(route('docs.webhooks'))->assertSuccessful();

    foreach (['X-Portal-Event', 'X-Portal-Delivery', 'X-Portal-Timestamp', 'X-Portal-Signature'] as $header) {
        $response->assertSee($header);
    }

    // Die Formel selbst, in der Schreibweise, in der sie auch im Job steht.
    $response->assertSee("hash_hmac('sha256', timestamp + '.' + rawBody, secret)");

    /*
     * Der Unterschied zu GitHub, der einen Empfaenger sonst eine Stunde kostet: dort
     * traegt der Header ein `sha256=`-Praefix, hier ist es nackter Hex. Die Seite muss
     * das ausdruecklich sagen — der Fehler ist an der Signatur nicht zu erkennen, sie
     * stimmt einfach nie.
     */
    $response->assertSee('sha256=');
    $response->assertSee('without');
});

it('carries a copy-paste typescript snippet with the two rules that make it work', function () {
    $response = $this->get(route('docs.webhooks'))->assertSuccessful();

    // Der Schnipsel selbst.
    // Ohne escape=false: die Seite gibt den Schnipsel durch {{ }} aus, Apostrophe
    // stehen im HTML also als &#039;. assertSee() escapt per Default genauso.
    $response->assertSee("import { createHmac, timingSafeEqual } from 'node:crypto';");
    $response->assertSee('export function verifyPortalWebhook(');
    $response->assertSee(".digest('hex')");

    // Regel 1 — gegen den ROHKOERPER, vor dem Parsen. Ohne das passt der HMAC nie,
    // und die Fehlermeldung lautet trotzdem nur "bad signature".
    $response->assertSee('Verify the RAW body');
    $response->assertSee("express.raw({ type: '*/*' })");

    // Regel 2 — konstante Zeit.
    $response->assertSee('timingSafeEqual(a, b)');

    // Regel 3 — Alter des Zeitstempels, gegen Replay.
    $response->assertSee('MAX_AGE_SECONDS');
});

it('spells out the deletion semantics, the dedupe keys and the way out of a gap', function () {
    $response = $this->get(route('docs.webhooks'))->assertSuccessful();

    // data: null plus previous — der einzige Weg, von einer Loeschung zu erfahren,
    // weil kein Model SoftDeletes nutzt. Inklusive der Felder je Ressource.
    $response->assertSee('&quot;previous&quot;', false);
    $response->assertSee('&quot;data&quot;: null', false);
    $response->assertSee('slug, city_id');
    $response->assertSee('meetup_id');

    // Dedupe ueber X-Portal-Delivery ODER sequence, und die Luecke schliesst
    // /api/changes — mit der echten URL, nicht mit einer erfundenen.
    $response->assertSee('sequence');
    $response->assertSee(route('api.changes.index'));

    // Ein Nutzer-Vorgang kann zwei Eintraege erzeugen.
    $response->assertSee('One write can produce two deliveries');
});

it('reads the retry schedule and the limits from the configuration, not from the page', function () {
    /*
     * Der eigentliche Beleg. Ohne diesen Test waere "kommt aus der Config" eine
     * Behauptung ueber Code, den niemand nachfaehrt — und der Retry-Plan auf der
     * Seite bliebe der von heute, auch wenn der gefahrene sich aendert.
     */
    config([
        'einundzwanzig.webhooks.timeout_seconds' => 7,
        'einundzwanzig.webhooks.auto_disable_after' => 3,
        // Zwei Stufen statt fuenf: 3 Versuche, kumuliert 1 h 30 min.
        'einundzwanzig.webhooks.backoff_seconds' => [1800, 3600],
        'einundzwanzig.webhooks.allowed_resources' => ['meetup'],
        'einundzwanzig.change_log.prune_days' => 90,
    ]);

    $response = $this->get(route('docs.webhooks'))->assertSuccessful();

    // Ohne das Markup dazwischen: im Fliesstext steht `2xx` in einem <code>-Element.
    $response->assertSee('within 7 seconds');
    $response->assertSee('3 attempts over 1 h 30 min');
    $response->assertSee('3 failed deliveries in a row disable the subscription');
    $response->assertSee('+30 min');
    $response->assertSee('+1 h 30 min');
    $response->assertSee('90 days');

    // Die abonnierbare Ressourcenliste ebenso: haette sie irgendwo hartkodiert
    // dringestanden, stuende `meetup-event` hier weiterhin als abonnierbar da.
    $response->assertDontSee("'meetup' | 'meetup-event'", false);
    $response->assertDontSee('meetup,meetup-event');
});

it('shows the approval gate exactly as it is configured', function () {
    /*
     * Beide Richtungen, weil beide vorkommen: mit Freigabe (Produktion) wartet ein
     * neues Abo, ohne Freigabe ist es sofort scharf. Eine Seite, die nur den einen
     * Fall kennt, laesst einen Konsumenten auf eine Freigabe warten, die niemand
     * erteilen muss — oder umgekehrt annehmen, es liefe schon.
     */
    config(['einundzwanzig.webhooks.require_approval' => true]);

    $this->get(route('docs.webhooks'))
        ->assertSuccessful()
        ->assertSee('Then it waits')
        ->assertSee('receives nothing until an');

    config(['einundzwanzig.webhooks.require_approval' => false]);

    $this->get(route('docs.webhooks'))
        ->assertSuccessful()
        ->assertSee('Live immediately')
        ->assertDontSee('Then it waits');
});

it('links to the resync endpoint, the api reference and its sibling page', function () {
    $this->get(route('docs.webhooks'))
        ->assertSuccessful()
        ->assertSee(route('api.changes.index'))
        ->assertSee(route('scramble.docs.ui'))
        // Die zwei Transport-Seiten muessen aufeinander zeigen: wer Webhooks liest,
        // soll wissen, dass es den schnelleren Weg mit weniger Zusagen auch gibt.
        ->assertSee(route('docs.websockets'));
});

it('describes the webhooks tag in the openapi document', function () {
    /*
     * Der sichtbare Reiter in der Scalar-UI. Anders als bei WebSockets legt Scramble
     * diesen Tag selbst an (aus `#[Group(name: 'Webhooks')]` am Controller) — die
     * Beschreibung wird deshalb ERGAENZT, nicht ein zweiter Tag angehaengt. Genau das
     * prueft die Duplikat-Zusicherung unten: zwei Reiter gleichen Namens waeren die
     * naheliegende falsche Umsetzung.
     */
    $document = $this->get(route('scramble.docs.document'))
        ->assertSuccessful()
        ->json();

    $tags = collect($document['tags'] ?? []);

    expect($tags->pluck('name'))->toContain('Webhooks')
        ->and($tags->firstWhere('name', 'Webhooks')['description'] ?? '')->toContain('/docs/webhooks')
        ->and($tags->pluck('name')->duplicates()->all())->toBe([])
        // Der WebSockets-Tag bleibt daneben stehen, mit seiner eigenen Beschreibung.
        ->and($tags->firstWhere('name', 'WebSockets')['description'] ?? '')->toContain('/docs/websockets');
});

it('links from the api reference to the webhook documentation', function () {
    /*
     * Die Pflicht aus der DoD: /docs/api zeigt sichtbar hierher. Der Tag oben ist die
     * Kuer und kann an Scalars Darstellung scheitern; die Einleitung wird immer
     * gerendert.
     */
    $document = $this->get(route('scramble.docs.document'))
        ->assertSuccessful()
        ->json();

    expect($document['info']['description'])
        ->toContain('## Webhooks')
        ->toContain('/docs/webhooks');
});
