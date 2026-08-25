<?php

use App\Enums\RecurrenceType;
use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/**
 * Eine Serie ueber MCP ist eine Serie — nicht ein Termin mit Serienbeschriftung.
 *
 * `CreateMeetupEventTool` rief `MeetupEvent::create($validated)` direkt und umging damit
 * `CreateMeetupEventSeries`, das der REST-Weg seit jeher nutzt. Mit `recurrence_type` UND
 * `recurrence_end_date` entstand EIN Termin, der die Serienregel trug, aber kein zweites
 * Vorkommen hatte.
 *
 * Der Schaden lag nicht im fehlenden Termin allein: `Meetup::recalculateActivity()` fragt
 * genau diese zwei Felder ab (`hasActiveRecurrence`) und haette das Meetup auf aktiv
 * gestellt, obwohl kein Termin in der Zukunft liegt — mitsamt Meldung in den
 * oeffentlichen Aenderungs-Feed. Genau der Fall, den P5 als „ohne echten Grund
 * ausgeloest" benannt hat.
 */
function meetupForMcpSeries(User $user): Meetup
{
    return Meetup::factory()->create(['created_by' => $user->id, 'is_active' => false, 'last_event_at' => null]);
}

it('creates a whole series through MCP, not a single event with series metadata', function () {
    $user = User::factory()->create();
    $meetup = meetupForMcpSeries($user);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup_id' => $meetup->id,
            'start' => now()->addWeek()->setTime(19, 0)->format('Y-m-d H:i:s'),
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addWeeks(5)->format('Y-m-d'),
        ])
        ->assertOk();

    $events = MeetupEvent::query()->where('meetup_id', $meetup->id)->get();

    expect($events->count())->toBeGreaterThan(1);
});

it('gives every occurrence of the MCP series the same recurrence_group', function () {
    $user = User::factory()->create();
    $meetup = meetupForMcpSeries($user);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup_id' => $meetup->id,
            'start' => now()->addWeek()->setTime(19, 0)->format('Y-m-d H:i:s'),
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addWeeks(5)->format('Y-m-d'),
        ])
        ->assertOk();

    $events = MeetupEvent::query()->where('meetup_id', $meetup->id)->get();

    expect($events->pluck('recurrence_group')->unique())->toHaveCount(1)
        ->and($events->first()->recurrence_group)->not->toBeNull();

    foreach ($events as $event) {
        expect($event->recurrence_type)->toBe(RecurrenceType::Weekly)
            ->and($event->recurrence_end_date)->not->toBeNull();
    }
});

/**
 * Die Gegenprobe: ohne Enddatum bleibt es ein einzelner Termin — genauso wie ueber REST.
 */
it('still creates a single event through MCP when no end date is given', function () {
    $user = User::factory()->create();
    $meetup = meetupForMcpSeries($user);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup_id' => $meetup->id,
            'start' => now()->addWeek()->setTime(19, 0)->format('Y-m-d H:i:s'),
            'recurrence_type' => 'weekly',
        ])
        ->assertOk();

    expect(MeetupEvent::query()->where('meetup_id', $meetup->id)->count())->toBe(1);
});

/**
 * Der eigentliche Grund fuer C3: eine Serie ohne Zukunftstermin haette das Meetup allein
 * ueber den `hasActiveRecurrence`-Zweig aktiv gestellt. Ueber die Action gibt es diesen
 * Zweig-ohne-Deckung nicht mehr — es liegt wirklich ein Termin in der Zukunft.
 */
it('leaves the meetup active for a real reason, not for a bare recurrence rule', function () {
    $user = User::factory()->create();
    $meetup = meetupForMcpSeries($user);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup_id' => $meetup->id,
            'start' => now()->addWeek()->setTime(19, 0)->format('Y-m-d H:i:s'),
            'recurrence_type' => 'weekly',
            'recurrence_end_date' => now()->addWeeks(5)->format('Y-m-d'),
        ])
        ->assertOk();

    expect($meetup->refresh()->is_active)->toBeTrue()
        ->and(MeetupEvent::query()->where('meetup_id', $meetup->id)->where('start', '>', now())->exists())
        ->toBeTrue();
});

/**
 * C4: `recurrence_type` traegt jetzt einen echten Enum-Cast. Bis P6 stand er in einer
 * Eigenschaft `$enumCasts`, die es in Eloquent nicht gibt — der Cast lief ins Leere.
 *
 * Das war KEINE Attrappe ohne Folgen: `meetups.create-edit-events` haelt die Auswahl in
 * `public ?RecurrenceType $recurrenceType` und weist ihr in `mount()` direkt das
 * Modell-Attribut zu. Aus der Datenbank geladen war das ein String — und jedes
 * Bearbeiten eines bestehenden Serientermins lief in einen 500er.
 *
 * Der Test laedt das Model deshalb AUSDRUECKLICH neu. Genau daran ist der Fehler
 * jahrelang vorbeigegangen: ein frisch erzeugtes Model haelt noch das Enum-Objekt aus
 * dem `create()`-Aufruf und stellt den echten Zustand nie her.
 */
it('opens the edit form for an existing recurring event loaded from the database', function () {
    $user = User::factory()->create();
    $meetup = meetupForMcpSeries($user);
    $id = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_end_date' => now()->addMonths(6),
    ])->id;

    $this->actingAs($user);

    Livewire::test('meetups.create-edit-events', [
        'meetup' => $meetup,
        'event' => MeetupEvent::query()->findOrFail($id),
    ])
        ->assertStatus(200)
        ->assertSet('seriesMode', true)
        ->assertSet('recurrenceType', RecurrenceType::Monthly);
});

it('casts recurrence_type to the enum when read back from the database', function () {
    $id = MeetupEvent::factory()->create(['recurrence_type' => RecurrenceType::Monthly])->id;

    expect(MeetupEvent::query()->findOrFail($id)->recurrence_type)->toBe(RecurrenceType::Monthly);
});

/**
 * Und die Ausgabe bleibt, was sie war: ein Backed Enum serialisiert in JSON zu seinem
 * Wert. Der Cast repariert das Formular, ohne die API zu verschieben.
 */
it('keeps delivering recurrence_type as its string value in the API', function () {
    $user = User::factory()->create();
    $meetup = meetupForMcpSeries($user);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_end_date' => now()->addMonths(6),
        'start' => now()->addWeek(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/my-meetup-events')
        ->assertSuccessful()
        ->assertJsonPath('data.0.recurrence_type', 'monthly');
});
