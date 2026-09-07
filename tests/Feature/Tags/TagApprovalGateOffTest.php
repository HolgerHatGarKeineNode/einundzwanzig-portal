<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The open state — issue #143
|--------------------------------------------------------------------------
|
| The owner switched the tag approval gate off: a tag is usable and visible to
| everyone the moment it is created. Nothing here sets
| einundzwanzig.tags.require_approval, on purpose — the shipped default is what
| production runs and has to be what an unconfigured test sees. The mirror
| cases, with the gate turned ON, live in TagEditorPermissionTest,
| TagModelTest, EventTagPickerTest and MeetupEventTagsMcpTest.
|
| The reporter's own scenario: two admins of one meetup, one of whom created a
| tag. Before #143 the second one did not see it in their picker at all.
|
*/

beforeEach(function () {
    $this->seed(TagSeeder::class);

    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
});

function gateOffTagCreatedBy(User $author, string $german): Tag
{
    test()->actingAs($author);

    Livewire::test('tags.picker', ['type' => 'meetup_event'])->call('createTag', $german);

    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de') === $german);
}

it('offers a non-editors brand-new tag to a different user in the picker', function () {
    $author = User::factory()->create(['nostr' => null]);
    $other = User::factory()->create(['nostr' => null]);

    $tag = gateOffTagCreatedBy($author, 'Lagerfeuerrunde');

    // Created live, not queued: can('create') is true for everyone with the gate off,
    // so the picker stamps approved_at instead of leaving it null.
    expect($tag->isApproved())->toBeTrue()
        ->and($tag->created_by)->toBe($author->id);

    $this->actingAs($other);

    $options = Livewire::test('tags.picker', ['type' => 'meetup_event'])->instance()->options;

    expect($options->pluck('id'))->toContain($tag->id);
});

it('offers a tag left over from the suggestion queue to a different user too', function () {
    // approved_at is deliberately NOT backfilled, so rows with a null there survive the
    // switch. They must not stay invisible — that was the reporter's symptom.
    $other = User::factory()->create(['nostr' => null]);
    $leftover = Tag::factory()
        ->pending(User::factory()->create(['nostr' => null]))
        ->named(['de' => 'Altlast'])
        ->create(['type' => 'meetup_event']);

    $this->actingAs($other);

    expect(Livewire::test('tags.picker', ['type' => 'meetup_event'])->instance()->options->pluck('id'))
        ->toContain($leftover->id);
});

it('returns a non-editors brand-new tag through the api, marked approved', function () {
    $author = User::factory()->create(['nostr' => null]);
    $owner = User::factory()->create(['nostr' => null]);

    $tag = gateOffTagCreatedBy($author, 'Lagerfeuerrunde');

    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $owner->id]);
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'created_by' => $owner->id]);
    $event->attachTag($tag);

    Sanctum::actingAs($owner);

    $payload = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    $row = collect($payload['tags'])->firstWhere('id', $tag->id);

    // `approved` stays in the payload — it is an editor-review marker now, not a
    // visibility gate, and it reads true because the tag was created live.
    expect($row)->not->toBeNull()
        ->and($row['name'])->toBe('Lagerfeuerrunde')
        ->and($row)->toHaveKey('approved')
        ->and($row['approved'])->toBeTrue();
});
