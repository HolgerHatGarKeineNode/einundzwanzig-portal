<?php

use App\Http\Resources\CityResource;
use App\Http\Resources\CourseEventResource;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LecturerResource;
use App\Http\Resources\MeetupEventResource;
use App\Http\Resources\MeetupResource;
use App\Models\ApiChange;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| Das Aenderungs-Log (Issue #29, Plan-Phase P1)
|--------------------------------------------------------------------------
|
| Der eigentliche Vertrag steht in "records the resource payload on create":
| `payload['data']` ist genau das, was die zugehoerige JsonResource in der
| kanonischen Relationsgestalt liefert. Die Gestalt wird hier NICHT aus dem
| ChangeRecorder gelesen, sondern noch einmal von Hand hingeschrieben — sonst
| pruefte der Test den Recorder gegen sich selbst und waere immer gruen.
|
*/

beforeEach(function (): void {
    // In der Testumgebung ist das Log per Default aus (phpunit.xml). Die Tests, die
    // es pruefen, schalten es selbst scharf.
    config()->set('einundzwanzig.change_log.enabled', true);
});

dataset('api resources', [
    'meetup',
    'meetup-event',
    'city',
    'course',
    'course-event',
    'lecturer',
]);

/**
 * Ein frisch angelegter Datensatz je Ressource, ohne Abhaengige — damit `delete()`
 * nicht an einem Fremdschluessel scheitert.
 */
function changeLogRecord(string $resource): Model
{
    return match ($resource) {
        'meetup' => Meetup::factory()->create(),
        'meetup-event' => MeetupEvent::factory()->create(),
        'city' => City::factory()->create(),
        'course' => Course::factory()->create(),
        'course-event' => CourseEvent::factory()->create(),
        'lecturer' => Lecturer::factory()->create(),
    };
}

/**
 * Eine Aenderung, die im Payload sichtbar wird.
 */
function changeLogUpdate(string $resource, Model $model): void
{
    match ($resource) {
        'meetup' => $model->update(['intro' => 'Neuer Einleitungstext.']),
        'meetup-event' => $model->update(['description' => 'Neue Beschreibung.']),
        'city' => $model->update(['population' => 424242]),
        'course' => $model->update(['description' => 'Neue Kursbeschreibung.']),
        'course-event' => $model->update(['location' => 'Neuer Ort 7']),
        'lecturer' => $model->update(['subtitle' => 'Neuer Untertitel']),
    };
}

/**
 * DIE KANONISCHE RELATIONSGESTALT, unabhaengig vom Recorder noch einmal notiert.
 * Weicht sie von {@see ChangeRecorder}::RESOURCES ab, faellt genau das hier auf.
 *
 * @return array{0: class-string<Model>, 1: class-string<JsonResource>, 2: array<int, string>}
 */
function changeLogShape(string $resource): array
{
    return match ($resource) {
        'meetup' => [Meetup::class, MeetupResource::class, ['media']],
        'meetup-event' => [MeetupEvent::class, MeetupEventResource::class, ['tags']],
        'city' => [City::class, CityResource::class, []],
        'course' => [Course::class, CourseResource::class, ['media']],
        'course-event' => [CourseEvent::class, CourseEventResource::class, ['tags', 'course', 'city']],
        'lecturer' => [Lecturer::class, LecturerResource::class, ['media']],
    };
}

/**
 * Was die Resource fuer diesen Datensatz liefert — nackt, ohne `data`-Wrapper, in der
 * Gestalt, in der sie auch ueber HTTP ginge.
 *
 * @return array<string, mixed>
 */
function changeLogExpectedData(string $resource, int $id): array
{
    [$modelClass, $resourceClass, $relations] = changeLogShape($resource);

    $model = $modelClass::query()->with($relations)->findOrFail($id);

    return json_decode(json_encode($resourceClass::make($model)->resolve()) ?: '{}', true);
}

/**
 * Die Identifikatoren, die ein `deleted` mitgeben muss.
 *
 * @return array<int, string>
 */
function changeLogPreviousKeys(string $resource): array
{
    return match ($resource) {
        'meetup' => ['slug', 'city_id'],
        'meetup-event' => ['meetup_id'],
        'city' => ['slug', 'country_id'],
        'course' => ['lecturer_id'],
        'course-event' => ['course_id', 'city_id'],
        'lecturer' => ['slug'],
    };
}

it('records exactly one row whose data equals the resource on create', function (string $resource): void {
    $model = changeLogRecord($resource);

    $rows = ApiChange::query()->where('resource', $resource)->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->action)->toBe('created')
        ->and($row->resource_id)->toBe($model->getKey())
        ->and($row->payload['action'])->toBe('created')
        ->and($row->payload['resource'])->toBe($resource)
        ->and($row->payload['id'])->toBe($model->getKey())
        // Die Sequenz IST die Zeilen-ID; sie ist der Resync-Cursor aus P2.
        ->and($row->payload['sequence'])->toBe($row->id)
        ->and($row->payload['api_version'])->toBe((string) config('scramble.info.version'))
        ->and($row->payload)->toHaveKey('links')
        ->and($row->payload)->not->toHaveKey('previous')
        ->and($row->payload['data'])->toBe(changeLogExpectedData($resource, $model->getKey()));
})->with('api resources');

it('records exactly one row whose data equals the resource on update', function (string $resource): void {
    $model = changeLogRecord($resource);
    ApiChange::query()->delete();

    changeLogUpdate($resource, $model);

    $rows = ApiChange::query()->where('resource', $resource)->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->action)->toBe('updated')
        ->and($row->resource_id)->toBe($model->getKey())
        ->and($row->payload['data'])->toBe(changeLogExpectedData($resource, $model->getKey()));
})->with('api resources');

it('records a delete without data but with the previous identifiers', function (string $resource): void {
    $model = changeLogRecord($resource);
    $before = $model->only(changeLogPreviousKeys($resource));
    ApiChange::query()->delete();

    $model->delete();

    $rows = ApiChange::query()->where('resource', $resource)->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->action)->toBe('deleted')
        ->and($row->resource_id)->toBe($model->getKey())
        // Kein SoftDelete im Projekt: der Datensatz ist wirklich weg, `data` waere
        // eine Erfindung.
        ->and($row->payload['data'])->toBeNull()
        ->and($row->payload['previous'])->toBe($before);

    foreach (changeLogPreviousKeys($resource) as $key) {
        expect($row->payload['previous'])->toHaveKey($key);
    }
})->with('api resources');

it('fills city_id where it is on the record itself and leaves country_code alone', function (): void {
    $country = Country::factory()->create(['code' => 'DE']);
    $city = City::factory()->create(['country_id' => $country->id]);

    $cityRow = ApiChange::query()->where('resource', 'city')->sole();

    // Die Stadt ist ihre eigene Stadt — das ist ohne Zusatz-Query zu haben.
    expect($cityRow->city_id)->toBe($city->id)
        // Der Laendercode nicht: dafuer muesste die country-Relation geladen werden,
        // und das ist eine Query pro Schreibvorgang fuer eine Spalte, die erst P7
        // liest. Sobald P7 `city.country` eager laedt, faellt der Wert nebenbei ab.
        ->and($cityRow->country_code)->toBeNull();

    $meetup = Meetup::factory()->create(['city_id' => $city->id]);

    expect(ApiChange::query()->where('resource', 'meetup')->sole()->city_id)->toBe($city->id);
});

it('links to the resource only where a show endpoint actually exists', function (): void {
    $lecturer = Lecturer::factory()->create();
    $meetup = Meetup::factory()->create();

    $lecturerRow = ApiChange::query()->where('resource', 'lecturer')->sole();
    $meetupRow = ApiChange::query()->where('resource', 'meetup')->sole();

    expect($lecturerRow->payload['links']['self'])
        ->toBe(route('api.lecturers.show', $lecturer->id))
        // /api/meetup hat heute nur einen index. Eine self-URL, die 404 liefert, waere
        // schlechter als keine — der verlaessliche Weg ist /api/changes (P2).
        ->and($meetupRow->payload['links']['self'])->toBeNull();
});

it('records the meetup activity change that saveQuietly would swallow', function (): void {
    $meetup = Meetup::factory()->create(['is_active' => false, 'last_event_at' => null]);
    ApiChange::query()->delete();

    // Loest ueber den MeetupEventObserver recalculateActivity() aus. Die Methode endet
    // auf saveQuietly() und feuert deshalb kein Model-Event — ohne den ausdruecklichen
    // Recorder-Aufruf in Meetup::recalculateActivity() bliebe der Wechsel unsichtbar.
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDays(7),
        'recurrence_type' => null,
    ]);

    $rows = ApiChange::query()->where('resource', 'meetup')->get();

    expect($meetup->fresh()->is_active)->toBeTrue()
        ->and($rows)->toHaveCount(1)
        ->and($rows->first()->action)->toBe('updated')
        ->and($rows->first()->payload['data']['is_active'])->toBeTrue();
});

it('records nothing when recalculating activity changes neither field', function (): void {
    $meetup = Meetup::factory()->create(['is_active' => false, 'last_event_at' => null]);
    ApiChange::query()->delete();

    // Kein Termin, kein Wechsel — der Normalfall des naechtlichen Laufs ueber alle
    // Meetups. Wuerde hier eine Zeile entstehen, waere das Log jede Nacht so lang wie
    // die Meetup-Tabelle.
    $meetup->recalculateActivity();

    expect(ApiChange::query()->count())->toBe(0);
});

it('records nothing while the kill switch is off', function (): void {
    config()->set('einundzwanzig.change_log.enabled', false);

    $city = City::factory()->create();
    $city->update(['population' => 1234]);
    $city->delete();

    expect(ApiChange::query()->count())->toBe(0);
});

it('records nothing inside a muted block and resumes afterwards', function (): void {
    ChangeRecorder::muted(function (): void {
        City::factory()->count(3)->create();
    });

    expect(ApiChange::query()->count())->toBe(0);

    City::factory()->create();

    expect(ApiChange::query()->where('resource', 'city')->count())->toBe(1);
});

it('resumes recording even when the muted block throws', function (): void {
    try {
        ChangeRecorder::muted(function (): void {
            City::factory()->create();

            throw new RuntimeException('Import abgebrochen');
        });
    } catch (RuntimeException) {
        // Erwartet — der Punkt des Tests ist, was danach gilt.
    }

    expect(ChangeRecorder::enabled())->toBeTrue();

    City::factory()->create();

    expect(ApiChange::query()->where('resource', 'city')->count())->toBe(1);
});

it('keeps the full object in the table but strips data from an oversized broadcast', function (): void {
    // rtrim, weil NormalizesText das nachlaufende Leerzeichen beim Speichern
    // entfernt. Die Aussage des Tests ist die GROESSE des Payloads, nicht sein
    // letztes Zeichen — ohne das rtrim vergliche er gegen einen Wert, den die
    // Datenbank so gar nicht mehr annimmt.
    $description = rtrim(str_repeat('Bitcoin. ', 1500)); // ~13,5 KB

    $event = MeetupEvent::factory()->create(['description' => $description]);

    $row = ApiChange::query()->where('resource', 'meetup-event')->sole();

    // Die Tabelle behaelt das vollstaendige Objekt — sie ist der Resync-Weg, und ein
    // gekuerzter Resync waere kein Resync.
    expect($row->payload['data']['description'])->toBe($description)
        ->and($row->payload['data'])->toBe(changeLogExpectedData('meetup-event', $event->id))
        ->and(strlen((string) json_encode($row->payload)))->toBeGreaterThan(ChangeRecorder::MAX_BROADCAST_BYTES);

    $broadcast = $row->broadcastPayload();

    // Reverb weist alles ueber max_request_size (10 000 Bytes) mit HTTP 413 ab.
    expect($broadcast['data'])->toBeNull()
        ->and($broadcast['truncated'])->toBeTrue()
        ->and($broadcast['sequence'])->toBe($row->id)
        ->and($broadcast['resource'])->toBe('meetup-event')
        ->and(strlen((string) json_encode($broadcast)))->toBeLessThanOrEqual(ChangeRecorder::MAX_BROADCAST_BYTES);
});

it('leaves a payload under the limit untouched', function (): void {
    $lecturer = Lecturer::factory()->create(['description' => 'Kurz.']);

    $row = ApiChange::query()->where('resource', 'lecturer')->sole();

    expect(strlen((string) json_encode($row->payload)))->toBeLessThanOrEqual(ChangeRecorder::MAX_BROADCAST_BYTES)
        ->and($row->broadcastPayload())->toBe($row->payload)
        ->and($row->broadcastPayload())->not->toHaveKey('truncated')
        ->and($row->broadcastPayload()['data']['id'])->toBe($lecturer->id);
});

it('ignores models that are not public API resources', function (): void {
    Country::factory()->create();

    expect(ApiChange::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Issue #30 — der Akteur (`user_id`) und seine Grenze zum Envelope
|--------------------------------------------------------------------------
*/

it('stamps user_id with the acting user but keeps it out of the payload', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    City::factory()->create();

    $row = ApiChange::query()->where('resource', 'city')->sole();

    expect($row->user_id)->toBe($user->id)
        ->and($row->payload)->not->toHaveKey('user_id')
        ->and($row->payload['data'])->not->toHaveKey('user_id');
});

it('leaves user_id null for a write without an authenticated user and still records it', function (): void {
    City::factory()->create();

    $row = ApiChange::query()->where('resource', 'city')->sole();

    expect($row->user_id)->toBeNull();
});
