<?php

use App\Models\SelfHostedService;
use App\Models\User;

// Das country-Segment 'de' in der Route ist der LÄNDER-Filter, nicht die Sprache:
// die Sprache kommt aus der Session, sonst greift der Fallback en-GB. Solange der
// Disclaimer-String in en.json leer war, fiel Laravel auf den deutschen Key zurück
// und die Assertions waren zufällig grün — mit echter englischer Übersetzung nicht.
beforeEach(function () {
    $this->withSession(['lang_country' => 'de-DE', 'locale' => 'de']);
});

it('shows the disclaimer and njump link on the services index', function () {
    SelfHostedService::factory()->for(
        User::factory()->create(['nostr' => 'npub1example']),
        'createdBy'
    )->create();

    $this->get(route('services.index', ['country' => 'de']))
        ->assertOk()
        ->assertSee('Keine Empfehlung von EINUNDZWANZIG')
        ->assertSee('npub')
        ->assertSee('https://njump.me/npub1example');
});

it('shows the disclaimer and njump link on the service landingpage', function () {
    $service = SelfHostedService::factory()->for(
        User::factory()->create(['nostr' => 'npub1example']),
        'createdBy'
    )->create();

    $this->get(route('services.landingpage', ['country' => 'de', 'service' => $service->slug]))
        ->assertOk()
        ->assertSee('Keine Empfehlung von EINUNDZWANZIG')
        ->assertSee('https://njump.me/npub1example');
});

it('omits the njump link for anonymous services', function () {
    $service = SelfHostedService::factory()->anonymous()->create();

    $this->get(route('services.landingpage', ['country' => 'de', 'service' => $service->slug]))
        ->assertOk()
        ->assertSee('Keine Empfehlung von EINUNDZWANZIG')
        ->assertDontSee('njump.me');
});
