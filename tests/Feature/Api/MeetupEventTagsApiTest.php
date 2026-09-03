<?php

use App\Models\ApiChange;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebhookSubscription;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TagSeeder::class);

    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $city->id]);
});

function anEventTag(string $german): Tag
{
    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === $german);
}

it('exposes title, end and tags on the public list endpoint', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'title' => 'Einsteigerabend',
        'start' => now()->addWeek()->setTime(19, 0),
        'end' => now()->addWeek()->setTime(22, 0),
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    // Explicit locale: Laravel's test client always sends a synthetic
    // Accept-Language header (Symfony's Request::create() default is
    // "en-us,en;q=0.5"), so leaving this implicit would make the assertion
    // depend on that test-client detail rather than on a real client's choice.
    $row = collect(
        $this->getJson('/api/meetup-events', ['Accept-Language' => 'de'])
            ->assertOk()
            ->json(),
    )->firstWhere('id', $event->id);

    expect($row['title'])->toBe('Einsteigerabend')
        ->and($row['end'])->not->toBeNull()
        ->and(collect($row['tags'])->pluck('name'))->toContain('Vortrag');
});

it('returns the tag name in the locale requested via the Accept-Language header', function () {
    $event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);
    $event->attachTag(anEventTag('Vortrag'));

    $row = collect(
        $this->getJson('/api/meetup-events', ['Accept-Language' => 'cs'])
            ->assertOk()
            ->json(),
    )->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Přednáška')
        ->and($row['tags'][0]['locale'])->toBe('cs');
});

it('returns the tag name in the locale requested via the ?locale= query parameter', function () {
    $event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);
    $event->attachTag(anEventTag('Vortrag'));

    // Sent together with a conflicting header to prove ?locale= wins.
    $row = collect(
        $this->getJson('/api/meetup-events?locale=cs', ['Accept-Language' => 'de'])
            ->assertOk()
            ->json(),
    )->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Přednáška')
        ->and($row['tags'][0]['locale'])->toBe('cs');
});

it('falls back to another translation and reports it when the requested locale has none', function () {
    $event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);

    $german = new Tag(['type' => 'meetup_event']);
    $german->setTranslation('name', 'de', 'Nur Deutsch');
    $german->approved_at = now();
    $german->save();

    $event->attachTag($german);

    $row = collect(
        $this->getJson('/api/meetup-events', ['Accept-Language' => 'cs'])
            ->assertOk()
            ->json(),
    )->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Nur Deutsch')
        ->and($row['tags'][0]['locale'])->toBe('de');
});

it('never returns an empty tag name, even for a german-only tag', function () {
    // The display chain must hold at the API boundary too.
    $event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);

    $german = new Tag(['type' => 'meetup_event']);
    $german->setTranslation('name', 'de', 'Nur Deutsch');
    $german->approved_at = now();
    $german->save();

    $event->attachTag($german);

    app()->setLocale('cs');

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Nur Deutsch')
        ->and($row['tags'][0]['locale'])->toBe('de');
});

/*
|--------------------------------------------------------------------------
| Issue #39 — the requested locale reaches TagResource too
|--------------------------------------------------------------------------
|
| The three tests above cover GET /api/meetup-events, which hand-builds its tag
| array in the controller. Everything else that carries tags goes through
| TagResource, which read app()->getLocale() and ignored both `?locale=` and
| Accept-Language — so the authenticated endpoints, the write responses and the
| change-log/webhook envelope all answered in German no matter what was asked.
|
| Note the field name difference, and that it is deliberate: the hand-built array
| above calls it `locale`, TagResource calls it `name_locale`. Renaming either
| would break existing parsers, so both stay.
|
*/
it('returns the tag name in the requested locale on my-meetup-events', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    $row = collect(
        $this->getJson('/api/my-meetup-events', ['Accept-Language' => 'cs'])
            ->assertOk()
            ->json('data'),
    )->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Přednáška')
        ->and($row['tags'][0]['name_locale'])->toBe('cs');
});

it('lets ?locale= win over a conflicting Accept-Language on my-meetup-events', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    // Both channels used at once, and they disagree: the query parameter is the
    // explicit one and has to win, because a client that cannot set headers has
    // nothing else to say it with.
    $row = collect(
        $this->getJson('/api/my-meetup-events?locale=cs', ['Accept-Language' => 'de'])
            ->assertOk()
            ->json('data'),
    )->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Přednáška')
        ->and($row['tags'][0]['name_locale'])->toBe('cs');
});

it('answers a PATCH in the requested locale', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
        'start' => now()->addWeek()->setTime(18, 0),
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    $response = $this->patchJson("/api/meetup-events/{$event->id}?locale=cs", [
        'title' => 'Umgeschrieben',
    ])->assertOk();

    expect($response->json('data.tags.0.name'))->toBe('Přednáška')
        ->and($response->json('data.tags.0.name_locale'))->toBe('cs');
});

it('keeps answering in the display-chain default when nothing was requested', function () {
    // The whole change is additive: no `?locale=`, no Accept-Language (TestCase
    // clears the test client's synthetic default), and the answer is what it was
    // before — app()->getLocale(), which is `de` here and, measured, stays `de`
    // even on the API group: SetApiLocale switches only the translator.
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    $row = collect($this->getJson('/api/my-meetup-events')->assertOk()->json('data'))
        ->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Vortrag')
        ->and($row['tags'][0]['name_locale'])->toBe('de');
});

/*
|--------------------------------------------------------------------------
| The same, in the webhook envelope
|--------------------------------------------------------------------------
|
| A queued delivery cannot resolve a locale — DeliverWebhookJob re-sends the
| stored bytes and a worker has no request. What it CAN carry is the language of
| the write request, because ChangeRecorder::data() resolves the resource with
| request() synchronously inside that request and freezes the result into
| api_changes.payload. This test pins that: the envelope of a PATCH sent with
| ?locale=cs reads Czech, and `name_locale` says so.
|
| QUEUE_CONNECTION=sync (phpunit.xml) runs the job the moment the recorder
| dispatches it, so Http::assertSent() sees the real POST body.
|
*/
it('carries the tag name of the write request locale into the webhook envelope', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    // Only now: the fixtures above would otherwise each queue a delivery of their
    // own and the assertion could not tell them apart.
    config()->set('einundzwanzig.change_log.enabled', true);
    WebhookSubscription::factory()->create(['resources' => ['meetup-event']]);
    Http::fake(['*' => Http::response('', 200)]);

    $this->patchJson("/api/meetup-events/{$event->id}?locale=cs", [
        'title' => 'Umgeschrieben',
    ])->assertOk();

    Http::assertSent(function ($request): bool {
        $payload = json_decode($request->body(), true);

        return $payload['resource'] === 'meetup-event'
            && $payload['action'] === 'updated'
            && $payload['data']['tags'][0]['name'] === 'Přednáška'
            && $payload['data']['tags'][0]['name_locale'] === 'cs';
    });
});

it('freezes the writer locale into the change log rather than the reader locale', function () {
    /*
     * The other half of the same fact, and the one a consumer has to plan for: the
     * envelope is written once, so `GET /api/changes` hands out the language of the
     * WRITE, whatever the reader asks for. `translations` travels with every tag for
     * exactly this reason.
     */
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
    ]);
    $event->attachTag(anEventTag('Vortrag'));

    config()->set('einundzwanzig.change_log.enabled', true);

    $this->patchJson("/api/meetup-events/{$event->id}?locale=cs", [
        'title' => 'Umgeschrieben',
    ])->assertOk();

    $change = ApiChange::query()->where('resource', 'meetup-event')->latest('id')->sole();

    expect($change->payload['data']['tags'][0]['name'])->toBe('Přednáška');

    // Read back in German — the stored envelope does not change with the reader.
    $read = collect(
        $this->getJson('/api/changes', ['Accept-Language' => 'de'])->assertOk()->json('changes'),
    )->firstWhere('sequence', $change->id);

    expect($read['data']['tags'][0]['name'])->toBe('Přednáška')
        ->and($read['data']['tags'][0]['name_locale'])->toBe('cs');
});

it('accepts title and end when creating through the api', function () {
    Sanctum::actingAs($user = User::factory()->create());
    // Created with the owner in place: the policy reads created_by at creation time.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->meetup->city_id,
        'created_by' => $user->id,
    ]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $this->meetup->id,
        'title' => 'Workshop-Abend',
        'start' => now()->addWeek()->setTime(18, 0)->toDateTimeString(),
        'end' => now()->addWeek()->setTime(21, 0)->toDateTimeString(),
        'location' => 'Irgendwo',
        'link' => 'https://example.com',
    ])->assertCreated();

    expect($response->json('data.title'))->toBe('Workshop-Abend')
        ->and($response->json('data.end'))->not->toBeNull();
});

it('rejects an end that lies before the start', function () {
    Sanctum::actingAs($user = User::factory()->create());
    // Created with the owner in place: the policy reads created_by at creation time.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->meetup->city_id,
        'created_by' => $user->id,
    ]);

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $this->meetup->id,
        'start' => now()->addWeek()->setTime(18, 0)->toDateTimeString(),
        'end' => now()->addWeek()->setTime(17, 0)->toDateTimeString(),
        'link' => 'https://example.com',
    ])->assertStatus(422)->assertJsonValidationErrors('end');
});

it('allows patching only the end without sending start', function () {
    // after:start must not fire when start is absent from the request — otherwise the
    // rule resolves "start" as a literal date and every such PATCH fails.
    Sanctum::actingAs($user = User::factory()->create());

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $user->id,
        'start' => now()->addWeek()->setTime(18, 0),
    ]);

    $this->patchJson("/api/meetup-events/{$event->id}", [
        'end' => now()->addWeek()->setTime(21, 0)->toDateTimeString(),
    ])->assertOk();

    expect($event->fresh()->end)->not->toBeNull();
});
