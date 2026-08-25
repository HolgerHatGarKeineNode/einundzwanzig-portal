<?php

use App\Http\Requests\Api\StoreMeetupRequest;
use App\Http\Requests\Api\UpdateMeetupRequest;
use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Meetup\CreateMeetupTool;
use App\Mcp\Tools\Meetup\UpdateMeetupTool;
use App\Models\City;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| `is_active` ist Ausgabe, nicht Eingabe (P6)
|--------------------------------------------------------------------------
|
| Zwei gegenlaeufige Zusicherungen, die zusammengehoeren und deshalb in einer Datei
| stehen. Wer eine davon allein liest, baut die andere versehentlich ab:
|
|  1. Ueber REST und MCP laesst sich das Feld nicht mehr SETZEN. Es ist ein Messwert aus
|     dem Terminbestand (Meetup::recalculateActivity), kein Wunsch des Aufrufers; ein
|     gesetzter Wert hielt nur bis zum naechsten Observer-Lauf.
|  2. Es wird weiterhin AUSGELIEFERT. MeetupResource ist oeffentlicher Vertrag, und der
|     Wechsel wird ausdruecklich in den Aenderungs-Feed gemeldet (Issue #29).
|
| Die Probe auf 1. muss den Wert VERAENDERN wollen: ein `is_active: true` auf einem
| Meetup, das ohnehin aktiv ist, wuerde auch bei kaputtem Code gruen aussehen. Deshalb
| startet jeder Fall gegen den jeweils anderen Zustand.
*/

it('does not let REST set is_active on create', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/meetup', [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => City::factory()->create()->id,
        'is_active' => true,
    ])->assertCreated();

    // Ein frisches Meetup ohne jeden Termin ist nicht aktiv — trotz der Eingabe.
    $response->assertJsonPath('data.is_active', false);
    expect(Meetup::query()->where('name', 'Einundzwanzig Ansbach')->value('is_active'))->toBeFalse();
});

it('does not let REST set is_active on update', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'is_active' => false]);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'name' => 'Plan B Lugano',
        'is_active' => true,
    ])->assertSuccessful()->assertJsonPath('data.name', 'Plan B Lugano');

    expect($meetup->refresh()->is_active)->toBeFalse();
});

it('does not let MCP set is_active on create', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateMeetupTool::class, [
            'name' => 'Einundzwanzig Bamberg',
            'city_id' => City::factory()->create()->id,
            'is_active' => true,
        ])
        ->assertOk();

    expect(Meetup::query()->where('name', 'Einundzwanzig Bamberg')->value('is_active'))->toBeFalse();
});

it('does not let MCP set is_active on update', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'is_active' => false]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupTool::class, [
            'id' => $meetup->id,
            'name' => 'Plan B Lugano',
            'is_active' => true,
        ])
        ->assertOk();

    expect($meetup->refresh()->is_active)->toBeFalse();
});

it('keeps is_active out of both write schemas so no client is told it may send it', function () {
    foreach ([new StoreMeetupRequest, new UpdateMeetupRequest] as $request) {
        expect($request->rules())->not->toHaveKey('is_active');
    }
});

it('still delivers is_active through the REST resource', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['name' => 'Einundzwanzig Nuernberg', 'created_by' => $user->id, 'is_active' => true]);

    $this->getJson('/api/my-meetups/'.$meetup->id)
        ->assertSuccessful()
        ->assertJsonPath('data.name', $meetup->name)
        ->assertJsonPath('data.is_active', true);
});

it('still delivers is_active through MCP', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'is_active' => true]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'name' => 'Einundzwanzig Fuerth'])
        ->assertOk()
        ->assertSee('is_active');
});
