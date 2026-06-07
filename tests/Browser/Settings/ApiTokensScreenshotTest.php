<?php

it('shows the api token management UI and the one-time token reveal', function () {
    $user = actingAsUser(['name' => 'Lecturer Demo', 'is_lecturer' => true]);

    // Pre-existing token so the "Aktive Tokens" table is populated.
    $user->createToken('Mein Laptop');

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
