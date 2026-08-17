<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
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

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($row['title'])->toBe('Einsteigerabend')
        ->and($row['end'])->not->toBeNull()
        ->and(collect($row['tags'])->pluck('name'))->toContain('Vortrag');
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
