<?php

use App\Events\ResourceChanged;
use App\Models\ApiChange;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| ResourceChanged — der Weg vom Observer auf den Kanal (Issue #29, Phase P4)
|--------------------------------------------------------------------------
|
| Der wichtigste Test dieser Datei ist "delivers a delete through a real
| queue run …". Er ist bewusst NICHT mit Event::fake() gebaut: ein
| Event::assertDispatched() prueft, dass ein Objekt entstanden ist, und waere
| auch dann gruen, wenn der Job es niemals ausliefern koennte. Genau das ist
| der Fehler, den Laravels `deleteWhenMissingModels = true` erzeugt — ein
| Broadcast-Job, dessen Model verschwunden ist, wird beim Ausfuehren STILL
| verworfen. Deshalb laeuft der Job hier wirklich, ueber die sync-Queue, mit
| echter Serialisierung, und ein Broadcaster am Ende der Kette schreibt mit,
| was tatsaechlich ankommt.
|
*/

/**
 * Ein Broadcaster, der nichts sendet, sondern protokolliert.
 *
 * Der letzte Halt vor dem Draht: was hier ankommt, ist genau das, was der
 * Pusher-/Reverb-Broadcaster ueber die Leitung schicken wuerde. Ein Fake auf
 * Event-Ebene saehe stattdessen nur die Absicht.
 */
class RecordingBroadcaster implements Broadcaster
{
    /**
     * @var array<int, array{channels: array<int, string>, event: string, payload: array<string, mixed>}>
     */
    public static array $sent = [];

    public function auth($request)
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        self::$sent[] = [
            'channels' => array_map(static fn ($channel): string => (string) $channel, $channels),
            'event' => $event,
            'payload' => $payload,
        ];
    }
}

beforeEach(function (): void {
    RecordingBroadcaster::$sent = [];

    // In der Testumgebung ist das Log per Default aus (phpunit.xml).
    config()->set('einundzwanzig.change_log.enabled', true);

    config()->set('broadcasting.connections.recording', ['driver' => 'recording']);
    config()->set('broadcasting.default', 'recording');
    Broadcast::extend('recording', fn (): Broadcaster => new RecordingBroadcaster);
});

/**
 * Die mitgeschriebenen Broadcasts einer Ressource, in Reihenfolge.
 *
 * Gefiltert, weil ein Termin-Write laut Plan zwei Ereignisse erzeugt: das
 * `meetup-event` und — ueber `recalculateActivity()` — zusaetzlich ein `meetup`.
 * Das ist gewollt und kein Doppelversand.
 *
 * @return array<int, array{channels: array<int, string>, event: string, payload: array<string, mixed>}>
 */
function broadcastsFor(string $resource): array
{
    return array_values(array_filter(
        RecordingBroadcaster::$sent,
        static fn (array $sent): bool => ($sent['payload']['resource'] ?? null) === $resource
    ));
}

/**
 * Ein Umschlag in der Gestalt, die {@see ChangeRecorder} baut — ohne Datenbank.
 *
 * @return array<string, mixed>
 */
function changeEnvelope(string $resource, string $action): array
{
    return [
        'action' => $action,
        'resource' => $resource,
        'id' => 1234,
        'sequence' => 90412,
        'occurred_at' => '2026-08-23T16:23:11+00:00',
        'api_version' => '2.0.0',
        'data' => ['id' => 1234],
        'links' => ['self' => null],
    ];
}

/*
|--------------------------------------------------------------------------
| Die Kerneigenschaft: kein Model im Event
|--------------------------------------------------------------------------
*/

it('never carries an eloquent model', function (): void {
    $reflection = new ReflectionClass(ResourceChanged::class);

    // 1. Kein Konstruktor-Parameter darf ein Model annehmen. Waere hier ein Model,
    //    laege es anschliessend am Objekt und ginge mit in die Queue.
    foreach ($reflection->getConstructor()->getParameters() as $parameter) {
        $type = $parameter->getType();
        $names = $type instanceof ReflectionNamedType ? [$type->getName()] : array_map(
            static fn (ReflectionNamedType $inner): string => $inner->getName(),
            $type?->getTypes() ?? []
        );

        foreach ($names as $name) {
            // `is_a(..., allow_string: true)` und nicht `is_subclass_of`: sonst rutschte
            // ausgerechnet der Typ `Model` selbst durch, weil er keine Unterklasse
            // seiner selbst ist.
            expect(class_exists($name) && is_a($name, Model::class, true))
                ->toBeFalse("Konstruktor-Parameter \${$parameter->getName()} nimmt ein Eloquent-Model ({$name}).");
        }
    }

    // 2. Keine Property darf ein Model halten.
    foreach ($reflection->getProperties() as $property) {
        $type = $property->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : null;

        expect($name !== null && class_exists($name) && is_a($name, Model::class, true))
            ->toBeFalse("Property \${$property->getName()} haelt ein Eloquent-Model ({$name}).");
    }

    // 3. Kein SerializesModels. Der Trait ist der Mechanismus, der ein Model beim
    //    Ausfuehren des Jobs nachlaedt — und bei einem `deleted` nie wiederfindet.
    expect(array_keys(class_uses_recursive(ResourceChanged::class)))
        ->not->toContain(SerializesModels::class);
});

it('serializes to a queue payload without a single model identifier', function (): void {
    $event = new ResourceChanged(changeEnvelope('meetup-event', 'deleted'));

    $serialized = serialize(new BroadcastEvent($event));

    // `ModelIdentifier` ist die Spur, die `SerializesModels` im Queue-Payload
    // hinterlaesst. Ist sie da, wird beim Ausfuehren nachgeladen — und ein
    // geloeschtes Model laesst den Job still verschwinden.
    expect($serialized)->not->toContain(ModelIdentifier::class)
        ->and($serialized)->not->toContain('App\\Models\\MeetupEvent');

    /** @var BroadcastEvent $revived */
    $revived = unserialize($serialized);

    expect($revived->event->broadcastWith())->toBe(changeEnvelope('meetup-event', 'deleted'));
});

/*
|--------------------------------------------------------------------------
| Der entscheidende Test: ein echtes Delete durch eine echte Queue
|--------------------------------------------------------------------------
*/

it('delivers a delete through a real queue run although the model is gone', function (): void {
    // Fixtures ohne Log aufbauen — sonst zaehlen die Anlege-Ereignisse mit.
    config()->set('einundzwanzig.change_log.enabled', false);
    $meetupEvent = MeetupEvent::factory()->create();
    $id = $meetupEvent->id;
    config()->set('einundzwanzig.change_log.enabled', true);

    // Der Beleg, dass die Queue wirklich serialisiert hat: unter der sync-Queue
    // baut Laravel denselben JSON-Payload wie unter Redis und weckt den Job daraus
    // wieder auf. Ohne diese Zusicherung koennte ein spaeterer Wechsel des
    // Queue-Treibers den Test entschaerfen, ohne ihn rot zu faerben.
    $rawJobBodies = [];
    Event::listen(JobProcessing::class, function (JobProcessing $processing) use (&$rawJobBodies): void {
        $rawJobBodies[] = $processing->job->getRawBody();
    });

    $meetupEvent->delete();

    // Das Model ist wirklich weg — kein SoftDeletes irgendwo in diesem Projekt.
    expect(MeetupEvent::find($id))->toBeNull();

    $broadcasts = broadcastsFor('meetup-event');

    expect($broadcasts)->toHaveCount(1);

    $sent = $broadcasts[0];

    expect($sent['event'])->toBe('meetup-event.deleted')
        ->and($sent['channels'])->toBe(['portal', 'meetup-events'])
        ->and($sent['payload']['action'])->toBe('deleted')
        ->and($sent['payload']['id'])->toBe($id)
        ->and($sent['payload']['data'])->toBeNull()
        // `previous` ist das Einzige, was ein Konsument nach einer Loeschung noch
        // zum Invalidieren in der Hand hat.
        ->and($sent['payload']['previous']['meetup_id'])->toBe($meetupEvent->meetup_id);

    // Der Umschlag ist vollstaendig, nicht nur die zwei Felder oben.
    $row = ApiChange::query()->where('resource', 'meetup-event')->where('action', 'deleted')->sole();

    expect(Arr::except($sent['payload'], ['socket']))->toBe($row->broadcastPayload())
        ->and($sent['payload']['sequence'])->toBe($row->id);

    expect($rawJobBodies)->not->toBeEmpty();
    expect(implode("\n", $rawJobBodies))
        ->toContain('Illuminate\\\\Broadcasting\\\\BroadcastEvent')
        ->not->toContain('ModelIdentifier');
});

/*
|--------------------------------------------------------------------------
| Kanaele
|--------------------------------------------------------------------------
*/

it('broadcasts a meetup-event on exactly portal and meetup-events', function (string $action): void {
    $channels = (new ResourceChanged(changeEnvelope('meetup-event', $action)))->broadcastOn();

    expect($channels)->toHaveCount(2)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and(array_map(strval(...), $channels))->toBe(['portal', 'meetup-events']);
})->with(['created', 'updated', 'deleted']);

it('broadcasts every other resource on portal alone', function (string $resource): void {
    $channels = (new ResourceChanged(changeEnvelope($resource, 'updated')))->broadcastOn();

    // `meetup-events` heisst so, weil es Meetup-Termine traegt. Ein Kanal, der auch
    // Staedte und Kurse fuehrte, sagte etwas zu, was er nicht haelt — und ein
    // oeffentlicher Pusher-Kanal hat keinen Rueckkanal, ueber den das auffiele.
    expect(array_map(strval(...), $channels))->toBe(['portal']);
})->with(['meetup', 'city', 'course', 'course-event', 'lecturer']);

it('names the event without a leading dot for every action', function (string $action): void {
    expect((new ResourceChanged(changeEnvelope('meetup-event', $action)))->broadcastAs())
        ->toBe("meetup-event.{$action}");
})->with(['created', 'updated', 'deleted']);

it('names the event after the resource in the payload', function (): void {
    expect((new ResourceChanged(changeEnvelope('course-event', 'created')))->broadcastAs())
        ->toBe('course-event.created')
        ->and((new ResourceChanged(changeEnvelope('lecturer', 'deleted')))->broadcastAs())
        ->toBe('lecturer.deleted');
});

it('never lets a leading dot back into the event name', function (string $resource): void {
    /*
     * Der Punkt ist CLIENT-Syntax und gehoert nicht in den Namen: was broadcastAs()
     * liefert, geht woertlich ueber den Draht (BroadcastEvent::handle()). Stuende er
     * hier, entfernte laravel-echo bei `.listen('.meetup-event.created')` genau einen
     * Punkt und abonnierte einen Namen, den niemand sendet — erfolgreich und fuer
     * immer still.
     *
     * Diese Zusicherung ist bewusst eine eigene und nicht bloss ein `toBe(...)`: ein
     * Gleichheitstest faellt mit dem Namen zusammen, wenn jemand beide zugleich
     * anfasst. Diese hier bleibt rot, egal wie der Name lautet.
     */
    foreach (['created', 'updated', 'deleted'] as $action) {
        $name = (new ResourceChanged(changeEnvelope($resource, $action)))->broadcastAs();

        expect($name)->not->toStartWith('.')
            ->and($name)->toBe("{$resource}.{$action}");
    }
})->with(['meetup', 'meetup-event', 'city', 'course', 'course-event', 'lecturer']);

it('puts the dotless name on the wire, not just in broadcastAs', function (): void {
    /*
     * Der Beleg am anderen Ende der Kette: nicht die Methode wird geprueft, sondern
     * das, was der Broadcaster wirklich zu sehen bekommt.
     */
    City::factory()->create();

    $sent = broadcastsFor('city');

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['event'])->toBe('city.created')
        ->and($sent[0]['event'])->not->toStartWith('.');
});

/*
|--------------------------------------------------------------------------
| Ein Format, nicht zwei
|--------------------------------------------------------------------------
*/

it('returns the payload from broadcastWith untouched', function (): void {
    $payload = changeEnvelope('city', 'created');

    expect((new ResourceChanged($payload))->broadcastWith())->toBe($payload);
});

it('sends exactly what ChangeRecorder::broadcastPayload produces', function (): void {
    $city = City::factory()->create();

    $row = ApiChange::query()->where('resource', 'city')->sole();
    $sent = broadcastsFor('city');

    expect($sent)->toHaveCount(1)
        // Kein zweites Format: der Kanal traegt denselben Umschlag wie /api/changes,
        // sonst braeuchte ein Konsument nach einem Verbindungsabriss zwei Parser.
        ->and(Arr::except($sent[0]['payload'], ['socket']))
        ->toBe(ChangeRecorder::broadcastPayload($row->payload))
        ->and(Arr::except($sent[0]['payload'], ['socket']))->toBe($row->broadcastPayload())
        ->and($sent[0]['payload']['data']['id'])->toBe($city->id);
});

it('strips data from an oversized broadcast while the row keeps everything', function (): void {
    $description = str_repeat('Bitcoin. ', 1500); // ~13,5 KB

    MeetupEvent::factory()->create(['description' => $description]);

    $row = ApiChange::query()->where('resource', 'meetup-event')->sole();
    $sent = broadcastsFor('meetup-event');

    expect($sent)->toHaveCount(1);

    $payload = Arr::except($sent[0]['payload'], ['socket']);

    // Reverb weist alles ueber max_request_size (10 000 Bytes) mit HTTP 413 ab.
    expect($payload['data'])->toBeNull()
        ->and($payload['truncated'])->toBeTrue()
        ->and(strlen((string) json_encode($payload)))->toBeLessThanOrEqual(ChangeRecorder::MAX_BROADCAST_BYTES)
        // Die Tabelle bleibt vollstaendig — sie ist der Resync-Weg.
        ->and($row->payload['data']['description'])->toBe($description)
        ->and($row->payload)->not->toHaveKey('truncated');
});

/*
|--------------------------------------------------------------------------
| Der Kill-Switch schaltet beides ab
|--------------------------------------------------------------------------
*/

it('dispatches nothing and writes nothing while the change log is off', function (): void {
    config()->set('einundzwanzig.change_log.enabled', false);

    // Nur dieses eine Event faken — ein pauschales Event::fake() legte auch die
    // Model-Events still und der Observer liefe nie.
    Event::fake([ResourceChanged::class]);

    $city = City::factory()->create();
    $city->update(['population' => 4242]);
    $city->delete();

    Event::assertNotDispatched(ResourceChanged::class);

    expect(ApiChange::query()->count())->toBe(0)
        ->and(RecordingBroadcaster::$sent)->toBe([]);
});

it('dispatches nothing and writes nothing inside a muted block', function (): void {
    Event::fake([ResourceChanged::class]);

    ChangeRecorder::muted(function (): void {
        Lecturer::factory()->create();
    });

    Event::assertNotDispatched(ResourceChanged::class);

    expect(ApiChange::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Ein kaputter Broadcast reisst den Schreibvorgang nicht mit
|--------------------------------------------------------------------------
*/

it('keeps the write and the row when the broadcaster throws', function (): void {
    Broadcast::extend('recording', function (): Broadcaster {
        return new class implements Broadcaster
        {
            public function auth($request)
            {
                return true;
            }

            public function validAuthenticationResponse($request, $result)
            {
                return $result;
            }

            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw new BroadcastException('Reverb ist nicht erreichbar');
            }
        };
    });

    Log::spy();

    // Der Recorder laeuft im Observer, mitten im Schreib-Request. Fiele die Ausnahme
    // durch, riebe ein nicht erreichbarer Reverb jedes Speichern im Portal auf.
    $city = City::factory()->create(['name' => 'Rosenheim']);

    expect(City::find($city->id)->name)->toBe('Rosenheim')
        ->and(ApiChange::query()->where('resource', 'city')->count())->toBe(1);

    // Still ist der Ausfall trotzdem nicht.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Broadcast der Aenderung fehlgeschlagen'
            && $context['resource'] === 'city'
            && $context['action'] === 'created')
        ->atLeast()->once();
});

/*
|--------------------------------------------------------------------------
| Der ganze Weg, je Ressource
|--------------------------------------------------------------------------
*/

it('broadcasts create, update and delete for every api resource', function (): void {
    config()->set('einundzwanzig.change_log.enabled', false);
    $meetup = Meetup::factory()->create();
    $course = Course::factory()->create();
    $courseEvent = CourseEvent::factory()->create();
    $lecturer = Lecturer::factory()->create();
    config()->set('einundzwanzig.change_log.enabled', true);

    $meetup->update(['intro' => 'Neuer Einleitungstext.']);
    $course->update(['description' => 'Neue Kursbeschreibung.']);
    $courseEvent->update(['location' => 'Neuer Ort 7']);
    $lecturer->update(['subtitle' => 'Neuer Untertitel']);

    foreach (['meetup', 'course', 'course-event', 'lecturer'] as $resource) {
        $sent = broadcastsFor($resource);

        expect($sent)->toHaveCount(1)
            ->and($sent[0]['event'])->toBe("{$resource}.updated")
            ->and($sent[0]['channels'])->toBe(['portal']);
    }
});
