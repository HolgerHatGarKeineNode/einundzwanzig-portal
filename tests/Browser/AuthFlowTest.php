<?php

it('renders the login page with the Nostr button and collapsed Lightning login', function () {
    $page = visit('/login');

    /*
     * Nostr is now the primary path; Lightning is deprecated and collapsed behind an
     * accordion, so its QR/connect button are not visible until expanded.
     *
     * Die Texte kommen aus __(), nicht als Literale: dieser Test stand vorher auf
     * Englisch, weil DomainMiddleware auf 127.0.0.1 nicht griff und die Sprache aus
     * Playwrights Accept-Language geraten wurde. Seit der Rueckfall greift, ist es
     * Deutsch — und ein Test, der an einer geratenen Sprache haengt, faellt beim
     * naechsten Mal genauso um.
     */
    $page->assertSee(__('Log in mit Nostr'))
        ->assertSee(__('Lightning-Login wird abgelöst'))
        ->assertSee(__('Lightning-Login anzeigen'))
        ->assertSee('Bitcoin, not blockchain')
        ->assertDontSee(__('Click to connect'))
        ->assertNoJavaScriptErrors();
});

it('reveals the Lightning QR and connect button when the accordion is expanded', function () {
    $page = visit('/login');

    $page->click(__('Lightning-Login anzeigen'))
        ->assertSee(__('Click to connect'))
        ->assertNoJavaScriptErrors();
});
