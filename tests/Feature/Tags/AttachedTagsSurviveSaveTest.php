<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Issue #143 — a tag already on the event survives a save
|--------------------------------------------------------------------------
|
| The reported defect. Two admins run one meetup. A created a tag, so with the
| approval gate on it was a pending suggestion only A could see. B opened an
| event carrying that tag and changed the description. allowedTags() re-filtered
| the whole selection through scopeSelectableBy(), the tag fell out of the
| allowed set, and syncTagsWithType() detached it. No error, no warning:
| tagIds [1] after mount, picker options [], zero tags afterwards.
|
| The follow-on made it worse. In a tags_required_countries country the event was
| now untagged, so B's NEXT save failed validation ("Bitte wähle mindestens einen
| Tag.") on an event B had never emptied, and that edit was discarded too.
|
| The gate is off since #143, which makes the scenario unreachable in practice —
| every tag is selectable, so nothing can fall out. It returns the moment the flag
| is turned back on, which is why the cases below run with the gate ON. That is
| the state where the defect actually bites and where the fix has to hold.
|
*/

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->czechia = Country::factory()->create(['code' => 'cz']);
});

/**
 * A meetup with two admins: the creator, and a second one who is a leader.
 *
 * @return array{0: Meetup, 1: User, 2: User}
 */
function meetupWithTwoAdmins(Country $country): array
{
    $city = City::factory()->create(['country_id' => $country->id]);

    $adminA = User::factory()->create(['nostr' => null]);
    $adminB = User::factory()->create(['nostr' => null]);

    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $adminA->id]);
    $meetup->users()->syncWithoutDetaching([$adminB->id => ['is_leader' => true]]);

    return [$meetup, $adminA, $adminB];
}

function eventTaggedBy(Meetup $meetup, User $author, string $german): array
{
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'created_by' => $author->id,
        'start' => now()->addWeek()->startOfMinute(),
        'recurrence_type' => null,
    ]);

    $tag = Tag::factory()->pending($author)->named(['de' => $german])->create(['type' => 'meetup_event']);
    $event->attachTag($tag);

    return [$event, $tag];
}

it('keeps a tag the saving admin cannot even see', function () {
    config(['einundzwanzig.tags.require_approval' => true]);

    [$meetup, $adminA, $adminB] = meetupWithTwoAdmins($this->country);
    [$event, $tag] = eventTaggedBy($meetup, $adminA, 'Lagerfeuerrunde');

    $this->actingAs($adminB);

    $component = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event]);

    // The precondition of the bug, asserted rather than assumed: B carries the id but
    // is offered nothing, so any naive re-filter drops it.
    expect($component->instance()->tagIds)->toBe([$tag->id])
        ->and(Livewire::test('tags.picker', ['type' => 'meetup_event'])->instance()->options)->toHaveCount(0);

    $component
        ->set('description', 'Neue Beschreibung')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($event->fresh()->description)->toBe('Neue Beschreibung');
});

it('does not strand the event in a country where tags are mandatory', function () {
    // The follow-on symptom: with the tag silently gone the NEXT save was rejected and
    // discarded an edit the organiser had made in good faith.
    config(['einundzwanzig.tags.require_approval' => true]);

    [$meetup, $adminA, $adminB] = meetupWithTwoAdmins($this->czechia);
    [$event, $tag] = eventTaggedBy($meetup, $adminA, 'Lagerfeuerrunde');

    $this->actingAs($adminB);

    expect(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->instance()->tagsRequired)->toBeTrue();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->set('description', 'Erste Änderung')
        ->call('save')
        ->assertHasNoErrors();

    // Second save, on a freshly mounted form — this is the one that used to fail
    // validation with "Bitte wähle mindestens einen Tag."
    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event->fresh()])
        ->set('description', 'Zweite Änderung')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->description)->toBe('Zweite Änderung')
        ->and($event->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('still refuses a tag that is neither selectable nor already attached', function () {
    // The negative control. Widening the allowed set must not turn into "anything the
    // request asks for": a crafted tagIds carrying a third party's suggestion that this
    // event does not carry is still dropped, while the attached one survives.
    config(['einundzwanzig.tags.require_approval' => true]);

    [$meetup, $adminA, $adminB] = meetupWithTwoAdmins($this->country);
    [$event, $attached] = eventTaggedBy($meetup, $adminA, 'Lagerfeuerrunde');

    $stranger = User::factory()->create(['nostr' => null]);
    $secret = Tag::factory()->pending($stranger)->named(['de' => 'Geheimtipp'])->create(['type' => 'meetup_event']);

    $this->actingAs($adminB);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->set('tagIds', [$attached->id, $secret->id])
        ->set('description', 'Neue Beschreibung')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->tags->pluck('id')->all())->toBe([$attached->id]);
});

it('refuses a tag from another group even with the gate off', function () {
    // With the gate off scopeSelectableBy() filters nothing, so this is the refusal that
    // is left — and it must survive the widening too. A library_item tag is attached to
    // no event and belongs to no picker for this form.
    [$meetup, $adminA, $adminB] = meetupWithTwoAdmins($this->country);
    [$event, $attached] = eventTaggedBy($meetup, $adminA, 'Lagerfeuerrunde');

    $foreign = Tag::factory()->named(['de' => 'Buchtipp'])->create(['type' => 'library_item']);

    $this->actingAs($adminB);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->set('tagIds', [$attached->id, $foreign->id])
        ->set('description', 'Neue Beschreibung')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->tags->pluck('id')->all())->toBe([$attached->id]);
});

it('leaves the series path alone, because a new event carries no tags yet', function () {
    // createEventSeries() resolves allowedTags() once for every occurrence, and it is
    // only reached when $event is null (save() branches on `seriesMode && ! $event`).
    // The widening therefore contributes nothing there — asserted, not assumed.
    config(['einundzwanzig.tags.require_approval' => true]);

    [$meetup, $adminA] = meetupWithTwoAdmins($this->country);

    $own = Tag::factory()->pending($adminA)->named(['de' => 'Lagerfeuerrunde'])->create(['type' => 'meetup_event']);
    $foreign = Tag::factory()->pending(User::factory()->create())->named(['de' => 'Geheimtipp'])->create(['type' => 'meetup_event']);

    $this->actingAs($adminA);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('endDate', now()->addWeeks(3)->format('Y-m-d'))
        ->set('recurrenceType', 'weekly')
        ->set('location', 'Marktplatz')
        ->set('description', 'Serientermin')
        ->set('links', [['url' => 'https://example.com', 'label' => null]])
        ->set('tagIds', [$own->id, $foreign->id])
        ->call('save')
        ->assertHasNoErrors();

    $created = MeetupEvent::query()->where('meetup_id', $meetup->id)->get();

    expect($created)->not->toBeEmpty();

    foreach ($created as $event) {
        expect($event->tags->pluck('id')->all())->toBe([$own->id]);
    }
});
