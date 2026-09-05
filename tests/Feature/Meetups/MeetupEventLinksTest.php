<?php

/*
|--------------------------------------------------------------------------
| Issue #70 — the organiser form carries a list of links, not one link
|--------------------------------------------------------------------------
|
| Server-side through Livewire::test() on purpose: what is at stake here is
| what gets STORED (the list, its order, the labels, the limit), and that is
| decided in save(), not in the browser.
|
*/

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function meetupEventLinkForm(Meetup $meetup, ?MeetupEvent $event = null): Testable
{
    $parameters = $event === null ? ['meetup' => $meetup] : ['meetup' => $meetup, 'event' => $event];

    return Livewire::test('meetups.create-edit-events', $parameters)
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Marktplatz')
        ->set('description', 'Ein Test-Event');
}

it('stores several links with their labels, in the order the organiser entered them', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    meetupEventLinkForm($meetup)
        ->set('links', [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->firstOrFail();

    expect($event->linkList())->toBe([
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ])
        // The deprecated column keeps mirroring the first entry, so the ICS feed and
        // the MCP tools keep working without knowing about #70.
        ->and($event->link)->toBe('https://www.meetup.com/bitcoin-berlin/');
});

it('stores an entry whose label was left empty as a bare URL', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    meetupEventLinkForm($meetup)
        ->set('links', [
            ['url' => 'https://luma.com/berlin', 'label' => ''],
            ['url' => 'https://t.me/berlin_btc', 'label' => '   '],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->firstOrFail();

    // Stored without a label key at all — the column never holds `"label": ""` — and
    // read back as an explicit null, so a consumer has one shape to handle.
    expect($event->links)->toBe([
        ['url' => 'https://luma.com/berlin'],
        ['url' => 'https://t.me/berlin_btc'],
    ])
        ->and($event->linkList())->toBe([
            ['url' => 'https://luma.com/berlin', 'label' => null],
            ['url' => 'https://t.me/berlin_btc', 'label' => null],
        ]);
});

it('drops a row that has a label but no URL', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    meetupEventLinkForm($meetup)
        ->set('links', [
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => '', 'label' => 'Telegram'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->firstOrFail()->linkList())
        ->toBe([['url' => 'https://luma.com/berlin', 'label' => 'Luma']]);
});

it('offers one empty row on a new event and adds a row on demand, up to five', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    $component = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertSet('links', [['url' => null, 'label' => null]]);

    foreach (range(2, MeetupEvent::MAX_LINKS) as $expectedCount) {
        $component->call('addLink');

        expect($component->get('links'))->toHaveCount($expectedCount);
    }

    // The button is gone at the limit, and a crafted call cannot get past it either.
    $component->assertDontSeeHtml('data-testid="add-link"')
        ->call('addLink');

    expect($component->get('links'))->toHaveCount(MeetupEvent::MAX_LINKS);
});

it('removes a row and closes the gap it leaves', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    $component = meetupEventLinkForm($meetup)
        ->set('links', [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
        ])
        ->call('removeLink', 1);

    // Re-indexed, not left with a hole at key 1 — the inputs are bound by position.
    expect($component->get('links'))->toBe([
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ]);

    $component->call('save')->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->firstOrFail()->linkList())->toBe([
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ]);
});

it('accepts exactly five links', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    $five = collect(range(1, 5))
        ->map(fn (int $number): array => ['url' => "https://example.com/{$number}", 'label' => null])
        ->all();

    meetupEventLinkForm($meetup)
        ->set('links', $five)
        ->call('save')
        ->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->firstOrFail()->linkList())->toHaveCount(5);
});

it('refuses a sixth link with a message instead of dropping it quietly', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    $six = collect(range(1, 6))
        ->map(fn (int $number): array => ['url' => "https://example.com/{$number}", 'label' => null])
        ->all();

    meetupEventLinkForm($meetup)
        ->set('links', $six)
        ->call('save')
        ->assertHasErrors(['links' => 'max']);

    expect(MeetupEvent::query()->count())->toBe(0);
});

it('shows the links of an existing event and can add another one to them', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'links' => [['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com']],
        'recurrence_type' => null,
    ]);

    meetupEventLinkForm($meetup, $event)
        ->assertSet('links', [['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com']])
        ->set('links.1', ['url' => 'https://luma.com/berlin', 'label' => 'Luma'])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->linkList())->toBe([
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
    ]);
});

it('offers the pre-#70 single link as the first row of an existing event', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'link' => 'https://example.com/old',
        'recurrence_type' => null,
    ]);

    // The state a row the backfill never reached would be in: `link` filled, `links`
    // still NULL. linkList() falls back to the old column, so the organiser sees it.
    DB::table('meetup_events')->where('id', $event->id)->update(['links' => null]);

    meetupEventLinkForm($meetup, $event->refresh())
        ->assertSet('links', [['url' => 'https://example.com/old', 'label' => null]]);
});

/*
 * `links` arriving as null on an UPDATE (gate finding on 341d3ee). The API is not the
 * only door this comes through — an internal caller that maps a nullable value onto the
 * column produces exactly this shape, which is why the rule lives on the model and not
 * in the Form Request.
 */
it('treats a null links on update as "not supplied" and keeps the stored list byte-identical', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'links' => [
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc'],
        ],
    ]);

    $before = DB::table('meetup_events')->where('id', $event->id)->value('links');

    $event->update(['links' => null, 'description' => 'Neuer Text']);

    expect(DB::table('meetup_events')->where('id', $event->id)->value('links'))->toBe($before)
        // The instance that did the save must not be left holding a null either — the
        // Livewire editor keeps using it after update().
        ->and($event->linkList())->toHaveCount(2)
        ->and($event->fresh()->linkList()[0])->toBe(['url' => 'https://luma.com/berlin', 'label' => 'Luma'])
        ->and($event->fresh()->description)->toBe('Neuer Text');
});

it('still clears the list on update when it is given as an empty array', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'links' => [['url' => 'https://luma.com/berlin', 'label' => 'Luma']],
    ]);

    $event->update(['links' => []]);

    expect($event->fresh()->linkList())->toBe([])
        ->and($event->fresh()->link)->toBeNull();
});

/*
 * Until #108 this expected ONE entry: the legacy field replaced the whole list, which
 * is exactly how an event with five links came back with one. `link` addresses entry
 * one, so the second entry is none of its business and stays.
 */
it('replaces only the first entry when the legacy field is the only one given', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'links' => [
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc'],
        ],
    ]);

    $event->update(['links' => null, 'link' => 'https://example.com/legacy']);

    expect($event->fresh()->linkList())->toBe([
        ['url' => 'https://example.com/legacy', 'label' => null],
        ['url' => 'https://t.me/berlin_btc', 'label' => null],
    ]);
});

/*
 * The form has no "not supplied" case and must not grow one: every save carries the
 * complete list the organiser sees, so an empty list means they removed every row. This
 * pins that a payload with no rows CLEARS rather than falling back to the `link` mirror
 * — the same fallback that ate the API list — and that the typed array property survives
 * a crafted null instead of throwing.
 */
it('clears the list when the form is saved with no rows, and survives a crafted null', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'links' => [['url' => 'https://luma.com/berlin', 'label' => 'Luma']],
        'recurrence_type' => null,
    ]);

    meetupEventLinkForm($meetup, $event)
        ->set('links', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->linkList())->toBe([])
        ->and($event->fresh()->link)->toBeNull();
});

it('gives every occurrence of a new series the same list of links', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-15')
        ->set('recurrenceType', 'weekly')
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('links', [
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $events = MeetupEvent::where('meetup_id', $meetup->id)->get();

    expect($events)->toHaveCount(3);

    foreach ($events as $event) {
        expect($event->linkList())->toBe([
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
        ]);
    }
});
