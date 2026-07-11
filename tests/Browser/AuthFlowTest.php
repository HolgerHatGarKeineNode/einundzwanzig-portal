<?php

it('renders the login page with the Nostr button and collapsed Lightning login', function () {
    $page = visit('/login');

    // Nostr is now the primary path; Lightning is deprecated and collapsed
    // behind an accordion, so its QR/connect button are not visible until
    // expanded. The browser renders the English locale.
    $page->assertSee('Log in with Nostr')
        ->assertSee('Lightning login is being retired')
        ->assertSee('Show Lightning login')
        ->assertSee('Bitcoin, not blockchain')
        ->assertDontSee('Click to connect')
        ->assertNoJavaScriptErrors();
});

it('reveals the Lightning QR and connect button when the accordion is expanded', function () {
    $page = visit('/login');

    $page->click('Show Lightning login')
        ->assertSee('Click to connect')
        ->assertNoJavaScriptErrors();
});
