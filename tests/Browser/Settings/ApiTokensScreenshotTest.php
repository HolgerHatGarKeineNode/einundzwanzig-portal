<?php

it('shows the api token management UI and the one-time token reveal', function () {
    // `is_lecturer` stand hier ohne Wirkung: die Token-Seite fragt es nirgends ab, und
    // seit dem Abbau der Pruefung gatet das Flag auch keine Policy mehr. Der Name bleibt.
    $user = actingAsUser(['name' => 'Lecturer Demo']);

    // Pre-existing token so the "Aktive Tokens" table is populated.
    $user->createToken('Mein Laptop');

    // Das /de-Präfix ist der LÄNDER-Filter, nicht die Sprache: die Sprache kommt
    // aus der Session, sonst greift der Fallback en-GB. Solange die Token-Strings
    // in en.json leer waren, fiel Laravel auf den deutschen Key zurück und der
    // Test war zufällig grün — mit echter englischer Übersetzung nicht mehr.
    $this->withSession(['lang_country' => 'de-DE', 'locale' => 'de']);

    $page = visit('/de/settings/api-tokens');

    $page->assertSee('API Tokens')
        ->fill('name', 'Externer Kurs-Sync')
        ->click('Token erstellen')
        ->wait(1)
        ->assertSee('Dein neues API Token')
        ->assertSee('Aktive Tokens')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'settings-api-tokens');
});
