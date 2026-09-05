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

    expect($options)->toHaveCount(16)
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
        ->set('links', [['url' => 'https://example.com', 'label' => null]])
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
        ->set('links', [['url' => 'https://example.com', 'label' => null]]);

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
        ->set('links', [['url' => 'https://example.com', 'label' => null]])
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

it('names a new tag only in the language it was typed in', function () {
    /*
     * Replaces "names a new tag in every locale so the other eight can find it".
     * That copy was not a translation, and it was what made the picker's
     * "only available in :lang" line unreachable — see the case below and the
     * createTag() docblock.
     */
    $this->actingAs(editorUserForPicker());
    app()->setLocale('cs');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])->call('createTag', 'Rodiny s dětmi');

    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'cs', false) === 'Rodiny s dětmi');

    expect($tag)->not->toBeNull()
        ->and($tag->source_locale)->toBe('cs')
        ->and($tag->getTranslations('name'))->toBe(['cs' => 'Rodiny s dětmi']);

    foreach (['de', 'en', 'es', 'hu', 'lv', 'nl', 'pl', 'pt'] as $untouched) {
        expect($tag->getTranslation('name', $untouched, false))->toBe('');
    }
});

it('marks a tag created in another language as a foreign-language label', function () {
    /*
     * The defect this whole change exists for: the warning at picker.blade.php could
     * never fire for a tag the picker itself had created, because the typed name was
     * written into all nine locales. Reverting createTag() to that write reddens the
     * assertSee AND both expectations below.
     */
    $this->actingAs(editorUserForPicker());
    app()->setLocale('cs');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])->call('createTag', 'Rodiny s dětmi');

    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'cs', false) === 'Rodiny s dětmi');

    app()->setLocale('nl');

    // What the Dutch organiser actually reads in the option row. Asserted before the
    // model expectations on purpose, so a red run proves THIS line can fail.
    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->assertSee('Rodiny s dětmi')
        ->assertSee('alleen beschikbaar in CS');

    expect($tag->displayLocale('nl'))->toBe('cs')
        ->and($tag->isDisplayNameSubstituted('nl'))->toBeTrue();
});

it('does not mark a seeded tag that carries all nine translations', function () {
    // The other side of the same coin: the marker must stay off where the tag really
    // does have a name in the reader's language, or it becomes noise on every row.
    $this->actingAs(editorUserForPicker());
    app()->setLocale('nl');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->assertSee('Lezing')                        // "Vortrag" in Dutch, from the seeder
        ->assertDontSee('alleen beschikbaar in');
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

it('catches a duplicate of a tag that carries only one language', function () {
    /*
     * The duplicate guard walks all nine locales and a single-locale tag answers ''
     * for eight of them. Since a tag created through the picker now has exactly one
     * name, this is the shape the guard meets most often.
     */
    $this->actingAs(editorUserForPicker());
    app()->setLocale('cs');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])->call('createTag', 'Rodiny s dětmi');

    $created = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'cs', false) === 'Rodiny s dětmi');
    $countBefore = Tag::query()->where('type', 'meetup_event')->count();

    app()->setLocale('nl');

    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->call('createTag', '  rodiny s DĚTMI ')   // different case and padding
        ->assertSet('tagIds', [$created->id]);

    expect(Tag::query()->where('type', 'meetup_event')->count())->toBe($countBefore);
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

it('creates the typed tag through the create action, not through the model', function () {
    /*
     * Flux' Vorgabe: der Suchtext haengt an einer eigenen Property, und die
     * Anlege-Zeile ruft die Aktion per wire:click. Vorher schrieb Flux den Text bei
     * ENTER nach `tagIds`, wo ein Array erwartet wird — der 500er
     * "Cannot assign string to property ... of type array" vom 2026-08-23.
     */
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('tags.picker', ['type' => 'meetup_event'])
        ->set('search', 'Einsteigerabend')
        ->call('createTag')
        ->assertSet('search', '')
        ->assertSet('tagIds', fn ($tagIds): bool => is_array($tagIds) && count($tagIds) === 1);

    $created = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Einsteigerabend');

    expect($created)->not->toBeNull();
});

it('selects the existing tag when the typed name already exists', function () {
    // Kein Duplikat: die Anlege-Zeile waehlt einen vorhandenen Namen nur aus.
    $user = User::factory()->create();
    $existing = Tag::query()->where('type', 'meetup_event')->first();
    $countBefore = Tag::query()->where('type', 'meetup_event')->count();

    Livewire::actingAs($user)
        ->test('tags.picker', ['type' => 'meetup_event'])
        ->set('search', $existing->getTranslation('name', 'de'))
        ->call('createTag')
        ->assertSet('tagIds', [$existing->id])
        ->assertSet('search', '');

    expect(Tag::query()->where('type', 'meetup_event')->count())->toBe($countBefore);
});

it('adds to the picks instead of replacing them', function () {
    /*
     * Die Pillbox trug kein `multiple` und war damit eine Einfachauswahl: jeder Klick
     * tauschte den einen Pill aus (gemeldet am 2026-08-23 mit Bildschirmfoto). Der
     * Browser-Test in tests/Browser/TagPickerMultiSelectTest.php prueft das Attribut
     * dort, wo es wirkt; hier steht die Seite des Servers.
     */
    $user = User::factory()->create();
    [$first, $second] = Tag::query()->where('type', 'meetup_event')->take(2)->get()->all();

    Livewire::actingAs($user)
        ->test('tags.picker', ['type' => 'meetup_event'])
        ->set('tagIds', [$first->id])
        ->set('tagIds', [$first->id, $second->id])
        ->assertSet('tagIds', [$first->id, $second->id]);
});

it('drops values that are not ids', function () {
    /*
     * Das Elternformular validiert gegen `array` und fuettert whereIn() damit —
     * was hier durchgeht, landet in einer Abfrage.
     */
    $user = User::factory()->create();
    $tag = Tag::query()->where('type', 'meetup_event')->first();

    Livewire::actingAs($user)
        ->test('tags.picker', ['type' => 'meetup_event'])
        ->set('tagIds', [$tag->id, 'kein-id', null, $tag->id])
        ->assertSet('tagIds', [$tag->id]);
});

it('keeps only numeric ids in the model', function () {
    /*
     * Das Elternformular gibt den Wert ungeprueft in whereIn() — was hier durchgeht,
     * landet in einer Abfrage.
     */
    $user = User::factory()->create();
    $tag = Tag::query()->where('type', 'meetup_event')->first();

    Livewire::actingAs($user)
        ->test('tags.picker', ['type' => 'meetup_event'])
        ->set('tagIds', [$tag->id, 'kein-id', null, $tag->id])
        ->assertSet('tagIds', [$tag->id]);
});

it('survives a typed name arriving at the parent form too', function () {
    /*
     * Ueber #[Modelable] landet derselbe Wert im Formular. Trug dessen Property den
     * Typ `array`, war das ein 500, bevor der Waehler ueberhaupt zum Zug kam.
     */
    $user = User::factory()->create();
    $meetup = meetupInCountry('de', $user);

    Livewire::actingAs($user)
        ->test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('tagIds', 'Einsteigerabend')
        ->assertSet('tagIds', fn ($tagIds): bool => is_array($tagIds));
});
