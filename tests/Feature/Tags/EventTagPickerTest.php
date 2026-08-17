<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

function meetupInCountry(string $code, User $owner): Meetup
{
    $country = Country::factory()->create(['code' => $code]);
    $city = City::factory()->create(['country_id' => $country->id]);

    return Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $owner->id]);
}

function eventTag(string $german): Tag
{
    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === $german);
}

beforeEach(function () {
    $this->seed(TagSeeder::class);
});

it('renders the picker inside the event form', function () {
    $meetup = meetupInCountry('de', actingAsUser());

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertOk()
        ->assertSeeLivewire('tags.picker');
});

it('offers only event tags, not the library vocabulary', function () {
    actingAsUser();

    $options = Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->instance()->options;

    expect($options)->toHaveCount(15)
        ->and($options->pluck('type')->unique()->all())->toBe(['meetup_event']);
});

it('sorts featured tags to the front', function () {
    actingAsUser();

    $options = Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->instance()->options;

    expect($options->take(7)->pluck('featured')->unique()->all())->toBe([true])
        ->and($options->where('featured', true))->toHaveCount(7);
});

it('builds search aliases across all nine locales', function () {
    actingAsUser();

    $picker = Livewire::test('tags.picker', ['type' => 'meetup_event'])->instance();
    $aliases = $picker->aliasesFor(eventTag('Vortrag'));

    // Names and slugs from every locale, so a Czech search hits a German-only tag.
    expect($aliases)->toContain('Vortrag', 'Talk', 'Přednáška', 'Prelekcja')
        ->and($aliases)->toContain('vortrag', 'prednaska');
});

it('saves the picked tags on a new event', function () {
    $meetup = meetupInCountry('de', actingAsUser());
    $talk = eventTag('Vortrag');
    $beginners = eventTag('Einsteiger');

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->set('tagIds', [$talk->id, $beginners->id])
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();

    expect($event->tags->pluck('id')->sort()->values()->all())
        ->toBe(collect([$talk->id, $beginners->id])->sort()->values()->all());
});

it('loads existing tags when editing', function () {
    $meetup = meetupInCountry('de', actingAsUser());
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);
    $tag = eventTag('Workshop');
    $event->attachTag($tag);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('tagIds', [$tag->id]);
});

it('requires a tag in czechia but nowhere else', function () {
    $owner = actingAsUser();
    $czech = meetupInCountry('cz', $owner);
    $german = meetupInCountry('de', $owner);

    $fill = fn ($test) => $test
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Místo')
        ->set('description', 'Popis')
        ->set('link', 'https://example.com');

    $fill(Livewire::test('meetups.create-edit-events', ['meetup' => $czech]))
        ->set('tagIds', [])
        ->call('save')
        ->assertHasErrors('tagIds');

    $fill(Livewire::test('meetups.create-edit-events', ['meetup' => $german]))
        ->set('tagIds', [])
        ->call('save')
        ->assertHasNoErrors();
});

it('reads the requirement from the meetup country, not the browsed one', function () {
    $owner = actingAsUser();
    $czech = meetupInCountry('cz', $owner);

    expect(Livewire::test('meetups.create-edit-events', ['meetup' => $czech])
        ->instance()->tagsRequired)->toBeTrue();

    $german = meetupInCountry('de', $owner);

    expect(Livewire::test('meetups.create-edit-events', ['meetup' => $german])
        ->instance()->tagsRequired)->toBeFalse();
});

it('refuses a tag id the user was never offered', function () {
    // A crafted request must not attach someone else's pending suggestion.
    $owner = actingAsUser();
    $meetup = meetupInCountry('de', $owner);

    $stranger = User::factory()->create();
    $secret = Tag::factory()->pending($stranger)->create(['type' => 'meetup_event']);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->set('tagIds', [$secret->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->first()->tags)->toHaveCount(0);
});

function editorUserForPicker(): User
{
    return User::factory()->create(['nostr' => config('einundzwanzig.tag_editors')[0]]);
}

it('lets an editor create a live tag straight from the picker', function () {
    $this->actingAs(editorUserForPicker());

    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', 'Lagerfeuerrunde')
        ->assertHasNoErrors();

    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Lagerfeuerrunde');

    expect($tag)->not->toBeNull()
        ->and($tag->isApproved())->toBeTrue()
        ->and($tag->created_by)->toBe(auth()->id());
});

it('stores a non-editors tag as an unapproved suggestion but selects it anyway', function () {
    // The point: a mandatory-tag country must not become a dead end for them.
    $this->actingAs(User::factory()->create(['nostr' => null]));

    $component = Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', 'Lagerfeuerrunde');

    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Lagerfeuerrunde');

    expect($tag->isApproved())->toBeFalse();
    $component->assertSet('tagIds', [$tag->id]);
});

it('names a new tag in every locale so the other eight can find it', function () {
    $this->actingAs(editorUserForPicker());

    Livewire::test('tags.picker', ['type' => 'meetup_event'])->call('createTag', 'Lagerfeuerrunde');

    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Lagerfeuerrunde');

    foreach (config('einundzwanzig.tag_locales') as $locale) {
        expect($tag->getTranslation('name', $locale, false))->toBe('Lagerfeuerrunde');
    }
});

it('selects the existing tag instead of creating a duplicate', function () {
    $this->actingAs(editorUserForPicker());

    $before = Tag::query()->where('type', 'meetup_event')->count();
    $workshop = eventTag('Workshop');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', '  workshop  ')   // different case and padding
        ->assertSet('tagIds', [$workshop->id]);

    expect(Tag::query()->where('type', 'meetup_event')->count())->toBe($before);
});

it('catches a duplicate that exists only in another language', function () {
    // Typing the Czech name of a tag must not create a second row for it.
    $this->actingAs(editorUserForPicker());

    $before = Tag::query()->where('type', 'meetup_event')->count();
    $talk = eventTag('Vortrag');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', 'Přednáška')
        ->assertSet('tagIds', [$talk->id]);

    expect(Tag::query()->where('type', 'meetup_event')->count())->toBe($before);
});

it('ignores names that are too short or too long', function () {
    $this->actingAs(editorUserForPicker());
    $before = Tag::count();

    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', 'x')
        ->call('createTag', str_repeat('a', 61))
        ->call('createTag', '   ');

    expect(Tag::count())->toBe($before);
});

it('refuses tag creation for a guest', function () {
    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', 'Lagerfeuerrunde')
        ->assertStatus(403);
});
