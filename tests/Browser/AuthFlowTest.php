<?php

it('renders the login page with Google, Nostr and Lightning as first-class buttons', function () {
    $page = visit('/login');

    /*
     * Issue #147: Google must be on /login, not behind the Nostr button.
     * Lightning is still LNURL but a real button, not an accordion heading.
     * Texts come from __(); DomainMiddleware fallback is German.
     */
    $page->assertSee(__('Log in mit Google'))
        ->assertSee(__('Log in mit Nostr'))
        ->assertSee(__('Log in mit Lightning'))
        ->assertSee(__('Lightning-Login wird abgelöst'))
        ->assertSee('Bitcoin, not blockchain')
        ->assertDontSee(__('Lightning-Login anzeigen'))
        ->assertDontSee(__('Click to connect'))
        ->assertDontSee('window.nostr.js')
        ->assertDontSee('wnjParams')
        ->assertNoJavaScriptErrors();
});

it('reveals the Lightning QR and connect button when Lightning is opened', function () {
    $page = visit('/login');

    $page->click(__('Log in mit Lightning'))
        ->assertSee(__('Click to connect'))
        ->assertSee(__('Log in mit Google'))
        ->assertNoJavaScriptErrors();
});
