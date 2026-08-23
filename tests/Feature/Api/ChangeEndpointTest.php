<?php

use App\Http\Middleware\SetApiLocale;
use App\Http\Resources\MeetupEventResource;
use App\Models\ApiChange;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Psr\Log\LoggerInterface;

/*
|--------------------------------------------------------------------------
| GET /api/changes — der Resync-Weg (Issue #29, Plan-Phase P2)
|--------------------------------------------------------------------------
|
| Der Endpunkt ist das einzige Netz unter einem verpassten Broadcast, und der
| einzige Ort, an dem ein LOESCHEN ueberhaupt noch sichtbar ist: kein Model in
| diesem Projekt nutzt SoftDeletes, der Datensatz ist danach wirklich weg. Wer
| offline war, erfaehrt es hier oder nie — deshalb steht dieser Fall unten als
| eigener Test und nicht als Randnotiz.
|
*/

/**
 * Aufsteigend geschriebene Log-Zeilen, deren `occurred_at` in derselben Reihenfolge
 * laeuft wie ihre `id`. Die Factory streut ihren Zeitstempel sonst ueber 60 Tage —
 * fuer einen Cursor-Test waere das Rauschen.
 *
 * @return Collection<int, ApiChange>
 */
function apiChangeRows(int $count, ?string $resource = null): Collection
{
    $factory = $resource === null
        ? ApiChange::factory()
        : ApiChange::factory()->forResource($resource);

    return collect(range(1, $count))->map(fn (int $index): ApiChange => $factory->create([
        'occurred_at' => now()->subMinutes($count - $index),
    ]));
}

it('is throttled and localised like the other public read endpoints', function (): void {
    $route = Route::getRoutes()->getByName('api.changes.index');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())
        ->toContain('throttle:60,1')
        ->toContain(SetApiLocale::class);
});

it('is readable without authentication', function (): void {
    apiChangeRows(3);

    $this->getJson('/api/changes')
        ->assertOk()
        ->assertJsonStructure(['changes', 'next_since', 'has_more', 'cursor_expired']);
});

it('returns the changes after a sequence cursor, exclusively', function (): void {
    $rows = apiChangeRows(5);
    $cursor = $rows[2]->id;

    $response = $this->getJson("/api/changes?since={$cursor}")->assertOk();

    expect(collect($response->json('changes'))->pluck('sequence')->all())
        ->toBe([$rows[3]->id, $rows[4]->id])
        // Exklusiv: die Zeile, deren Sequenz der Konsument mitschickt, hat er schon.
        ->not->toContain($cursor)
        ->and($response->json('next_since'))->toBe($rows[4]->id)
        ->and($response->json('has_more'))->toBeFalse();
});

it('walks the cursor forward without duplicates and without gaps', function (): void {
    $rows = apiChangeRows(5);

    $seen = [];
    $cursor = 0;
    $pages = 0;

    do {
        $response = $this->getJson("/api/changes?since={$cursor}&limit=2")->assertOk();

        foreach ($response->json('changes') as $change) {
            $seen[] = $change['sequence'];
        }

        $cursor = $response->json('next_since');
        $pages++;
    } while ($response->json('has_more') && $pages < 10);

    // Weder eine Zeile doppelt noch eine ausgelassen — genau die beiden Fehler, die
    // ein Konsument nicht bemerkt: die Dopplung kostet nur Arbeit, die Luecke ist ein
    // Datensatz, von dem er nie erfaehrt.
    expect($seen)->toBe($rows->pluck('id')->all())
        ->and($seen)->toHaveCount(count(array_unique($seen)))
        ->and($pages)->toBe(3);

    // Und der naechste Aufruf mit dem letzten Cursor liefert nichts Neues, ohne den
    // Cursor zurueckzuspulen.
    $empty = $this->getJson("/api/changes?since={$cursor}")->assertOk();

    expect($empty->json('changes'))->toBe([])
        ->and($empty->json('next_since'))->toBe($rows->last()->id)
        ->and($empty->json('has_more'))->toBeFalse();
});

it('accepts an ISO-8601 timestamp as the cursor', function (): void {
    $older = ApiChange::factory()->create(['occurred_at' => now()->subHours(3)]);
    $newer = ApiChange::factory()->create(['occurred_at' => now()->subHour()]);

    $since = now()->subHours(2)->toIso8601String();

    $response = $this->getJson('/api/changes?since='.urlencode($since))->assertOk();

    expect(collect($response->json('changes'))->pluck('sequence')->all())
        ->toBe([$newer->id])
        ->and($response->json('changes')[0]['id'])->toBe($newer->resource_id)
        ->and($response->json('next_since'))->toBe($newer->id);

    unset($older);
});

it('reports a deletion to a consumer that was offline while it happened', function (): void {
    // Das Log ist im Test per Default aus (phpunit.xml) — hier geht es um echte
    // Model-Events, also muss es scharf sein.
    config()->set('einundzwanzig.change_log.enabled', true);

    $meetup = Meetup::factory()->create();
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    // Der Stand, den der Konsument kennt, bevor die Verbindung abreisst.
    $cursor = ApiChange::query()->max('id');

    $meetupId = $event->meetup_id;
    $eventId = $event->id;
    $event->delete();

    expect(MeetupEvent::query()->find($eventId))->toBeNull();

    $response = $this->getJson("/api/changes?since={$cursor}&resource=meetup-event")->assertOk();

    $deletion = collect($response->json('changes'))
        ->firstWhere(fn (array $change): bool => $change['action'] === 'deleted');

    expect($deletion)->not->toBeNull()
        ->and($deletion['resource'])->toBe('meetup-event')
        ->and($deletion['id'])->toBe($eventId)
        // Kein SoftDelete: `data` waere eine Erfindung, `previous` ist der Schluessel
        // zum gezielten Cache-Invalidieren.
        ->and($deletion['data'])->toBeNull()
        ->and($deletion['previous'])->toBe(['meetup_id' => $meetupId]);
});

it('filters by a single resource and by several at once', function (): void {
    apiChangeRows(2, 'city');
    apiChangeRows(2, 'meetup');
    apiChangeRows(2, 'lecturer');

    $single = $this->getJson('/api/changes?since=0&resource=city')->assertOk();

    expect(collect($single->json('changes'))->pluck('resource')->unique()->all())
        ->toBe(['city'])
        ->and($single->json('changes'))->toHaveCount(2);

    $commaSeparated = $this->getJson('/api/changes?since=0&resource=city,meetup')->assertOk();

    expect(collect($commaSeparated->json('changes'))->pluck('resource')->unique()->sort()->values()->all())
        ->toBe(['city', 'meetup'])
        ->and($commaSeparated->json('changes'))->toHaveCount(4);

    $repeated = $this->getJson('/api/changes?since=0&resource[]=city&resource[]=lecturer')->assertOk();

    expect($repeated->json('changes'))->toHaveCount(4);
});

it('rejects an unknown resource instead of answering with an empty list', function (): void {
    apiChangeRows(2, 'city');

    // Eine leere Liste waere hier die schlechtere Antwort: ein Tippfehler im
    // Filternamen saehe genauso aus wie "es hat sich nichts geaendert", und der
    // Konsument wartete still auf Ereignisse, die er nie bekommt.
    $this->getJson('/api/changes?resource=citys')
        ->assertStatus(422)
        ->assertJsonValidationErrors('resource.0');

    $this->getJson('/api/changes?resource=city,podcast')
        ->assertStatus(422)
        ->assertJsonValidationErrors('resource.1');
});

it('rejects a cursor that is neither a sequence nor a timestamp', function (): void {
    $this->getJson('/api/changes?since=irgendwann')
        ->assertStatus(422)
        ->assertJsonValidationErrors('since');

    $this->getJson('/api/changes?since=-5')
        ->assertStatus(422)
        ->assertJsonValidationErrors('since');
});

it('caps the page at 100 entries by default and at 1000 on request', function (): void {
    $rows = apiChangeRows(150);

    $default = $this->getJson('/api/changes?since=0')->assertOk();

    expect($default->json('changes'))->toHaveCount(100)
        ->and($default->json('has_more'))->toBeTrue()
        ->and($default->json('next_since'))->toBe($rows[99]->id);

    expect($this->getJson('/api/changes?since=0&limit=1000')->assertOk()->json('changes'))
        ->toHaveCount(150);

    $this->getJson('/api/changes?since=0&limit=1001')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');

    $this->getJson('/api/changes?since=0&limit=0')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

it('returns the newest entries when no cursor is given, so a newcomer finds a start', function (): void {
    $rows = apiChangeRows(150);

    $response = $this->getJson('/api/changes?limit=10')->assertOk();

    // Die JUENGSTEN zehn, nicht die aeltesten — und nicht die ganze Tabelle. Wer ohne
    // Cursor kommt, will einen Startpunkt, keine 30 Tage Historie.
    expect(collect($response->json('changes'))->pluck('sequence')->all())
        ->toBe($rows->slice(140)->pluck('id')->values()->all())
        ->and($response->json('next_since'))->toBe($rows->last()->id)
        // Jenseits der juengsten Zeile gibt es nichts mehr.
        ->and($response->json('has_more'))->toBeFalse();
});

it('sorts ascending by sequence, whatever the cursor', function (): void {
    $rows = apiChangeRows(6);

    $sequences = collect($this->getJson('/api/changes?since=0')->assertOk()->json('changes'))
        ->pluck('sequence')
        ->all();

    expect($sequences)->toBe($rows->pluck('id')->all())
        ->and($sequences)->toBe(collect($sequences)->sort()->values()->all());
});

it('hands out exactly the envelope the recorder wrote', function (): void {
    config()->set('einundzwanzig.change_log.enabled', true);

    $event = MeetupEvent::factory()->create();

    $row = ApiChange::query()->where('resource', 'meetup-event')->sole();

    /*
     * Die Erwartung wird hier UNABHAENGIG gebaut — aus dem Payload-Vertrag des Plans
     * und der kanonischen Relationsgestalt (`tags`), nicht aus `$row->payload`. Sonst
     * pruefte der Test den Endpunkt gegen sich selbst und waere auch dann gruen, wenn
     * beide Seiten dasselbe Falsche liefern.
     */
    $expected = [
        'action' => 'created',
        'resource' => 'meetup-event',
        'id' => $event->id,
        'sequence' => $row->id,
        'occurred_at' => $row->occurred_at->toIso8601String(),
        'api_version' => (string) config('scramble.info.version'),
        'data' => json_decode(json_encode(
            MeetupEventResource::make(MeetupEvent::query()->with('tags')->findOrFail($event->id))->resolve()
        ) ?: '{}', true),
        'links' => ['self' => null],
    ];

    $change = $this->getJson('/api/changes?since=0&resource=meetup-event')
        ->assertOk()
        ->json('changes.0');

    // toBe ist hier Absicht: identische Schluesselreihenfolge und identische Typen,
    // nicht bloss gleicher Inhalt. Ab P4 geht genau dieses Envelope ueber den Kanal,
    // und ein Resync-Eintrag, der sich davon unterscheidet, waere ein zweites Format.
    expect($change)->toBe($expected)
        ->and(json_encode($change))->toBe(json_encode($row->payload));
});

it('says so when the cursor is older than everything still on file', function (): void {
    // Zwei Zeilen jenseits der Aufbewahrungsfrist, zwei innerhalb.
    $pruned = collect([
        ApiChange::factory()->olderThan(40)->create(),
        ApiChange::factory()->olderThan(35)->create(),
    ]);
    $kept = ApiChange::factory()->create(['occurred_at' => now()->subDay()]);

    // Der Cursor, den der Konsument vor seinem Ausfall hatte.
    $cursor = $pruned->last()->id;

    // Echt geprunt, nicht von Hand geloescht: derselbe Weg, der es in Produktion tut.
    $this->artisan('api-changes:prune')->assertSuccessful();

    expect(ApiChange::query()->min('id'))->toBe($kept->id);

    $response = $this->getJson("/api/changes?since={$cursor}")->assertOk();

    /*
     * Ohne dieses Feld waere die Antwort hier NICHT leer, sondern harmlos falsch: der
     * Konsument bekaeme die eine verbliebene Zeile, haette aber die geloeschten
     * Datensaetze dazwischen nie gesehen — und kein Model nutzt SoftDeletes, es gibt
     * also keinen zweiten Weg, davon zu erfahren.
     */
    expect($response->json('cursor_expired'))->toBeTrue()
        ->and($response->json('changes'))->toHaveCount(1)
        // Der Weiterarbeits-Cursor steht trotzdem in derselben Antwort: erst
        // Vollabgleich, dann ab hier weiter.
        ->and($response->json('next_since'))->toBe($kept->id);
});

it('says so for an ISO cursor that predates the oldest entry', function (): void {
    ApiChange::factory()->create(['occurred_at' => now()->subDay()]);

    $expired = $this->getJson('/api/changes?since='.urlencode(now()->subDays(45)->toIso8601String()))
        ->assertOk();

    expect($expired->json('cursor_expired'))->toBeTrue();

    $valid = $this->getJson('/api/changes?since='.urlencode(now()->subHours(2)->toIso8601String()))
        ->assertOk();

    expect($valid->json('cursor_expired'))->toBeFalse();
});

it('reports a valid cursor, a missing cursor and an empty log as not expired', function (): void {
    // Leeres Log: nichts ist abgelaufen, es ist nur nichts da. Ohne diesen Fall
    // meldete ein frisch aufgesetztes System jedem Konsumenten sofort Datenverlust.
    expect(ApiChange::query()->count())->toBe(0)
        ->and($this->getJson('/api/changes?since=999')->assertOk()->json('cursor_expired'))->toBeFalse()
        ->and($this->getJson('/api/changes')->assertOk()->json('cursor_expired'))->toBeFalse();

    $rows = apiChangeRows(3);

    expect($this->getJson("/api/changes?since={$rows[1]->id}")->assertOk()->json('cursor_expired'))->toBeFalse()
        // Auch der aelteste noch vorhandene Eintrag selbst ist ein gueltiger Cursor —
        // die Grenze liegt darunter, nicht auf ihm.
        ->and($this->getJson("/api/changes?since={$rows->first()->id}")->assertOk()->json('cursor_expired'))->toBeFalse()
        // Ohne Cursor gibt es nichts, was ablaufen koennte.
        ->and($this->getJson('/api/changes')->assertOk()->json('cursor_expired'))->toBeFalse();
});

it('counts every call in its own log channel without writing to the database', function (): void {
    apiChangeRows(2);

    $logger = Mockery::spy(LoggerInterface::class);
    Log::partialMock()->shouldReceive('channel')->with('api-changes')->andReturn($logger);

    DB::enableQueryLog();

    $this->getJson('/api/changes?since=1&resource=city&limit=25')->assertOk();

    $writes = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => (bool) preg_match('/^\s*(insert|update|delete)\b/i', $query['query']));

    DB::disableQueryLog();

    // Der Zaehler ist ein Log-Eintrag, kein INSERT: der Endpunkt wird gepollt, und
    // eine Zeile je Poll erzeugte genau die Schreiblast, die hier gemessen wird.
    expect($writes)->toBeEmpty();

    $logger->shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'api-changes.request'
                && $context['since'] === '1'
                && $context['resource'] === ['city']
                && $context['limit'] === 25
                // Keine IP im Klartext, sondern ein geschluesseltes Pseudonym: stabil
                // genug, um zwei Konsumenten zu unterscheiden, und nicht
                // ruecktauschbar. P3 fragt "wie viele und wie oft", nicht "wer".
                && $context['client'] === substr(hash_hmac('sha256', '127.0.0.1', (string) config('app.key')), 0, 12)
                && ! array_key_exists('ip', $context)
                && $context['user_agent'] !== ''
                && array_key_exists('returned', $context);
        })
        ->once();
});

it('appears in the generated openapi document', function (): void {
    $document = $this->getJson(route('scramble.docs.document'))->assertOk()->json();

    expect($document['paths'])->toHaveKey('/changes');

    $parameters = collect($document['paths']['/changes']['get']['parameters'] ?? [])
        ->pluck('name')
        ->all();

    // `resource[]` und nicht `resource`: Scramble leitet den Namen aus der
    // Array-Regel des Form Requests ab. Hier steht der Name, der wirklich im
    // Dokument landet — nicht der, den man erwarten wuerde.
    expect($parameters)->toContain('since')
        ->toContain('resource[]')
        ->toContain('limit');
});
