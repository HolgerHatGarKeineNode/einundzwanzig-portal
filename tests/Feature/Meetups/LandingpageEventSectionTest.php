<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| #45 — the upcoming-events section leads the meetup detail page
|--------------------------------------------------------------------------
|
| Two hooks the markup owes this file:
|
|   data-testid="upcoming-events"     the events section itself
|   data-testid="no-upcoming-events"  the empty state shown to a meetup that
|                                     has no upcoming event
|
| Both are asserted on the attribute, never on the German copy — and here
| that is not a matter of taste. "Kommende Veranstaltungen" also occurs
| inside the CSS comment that landingpage.blade.php ships in its <style>
| block for the map height, and that comment sits in the RIGHT column, i.e.
| before the section it talks about. Measured on ea91f59 with one upcoming
| event: the first occurrence of that string is at offset 614770 (the
| comment), the section heading only at 620616. A DOM-order test anchored on
| the heading would have measured a comment and reported an order that the
| page does not have.
|
| The whole point of #45 is ORDER, not presence: before the restructuring the
| section rendered after the two-column header block, below the fold. A test
| that only asserts the section exists stays green while it slides back down.
*/

/**
 * A meetup that renders both header blocks the events section has to beat.
 *
 * `intro` is pinned rather than left to the factory's faker paragraph: the
 * "Über uns" block is gated on it, and it is one of the two anchors below.
 */
function meetupWithHeaderBlocks(?User $leader = null): Meetup
{
    return Meetup::factory()->create([
        'intro' => 'Wir treffen uns jeden zweiten Donnerstag im Vereinsheim.',
        'created_by' => ($leader ?? User::factory()->create())->id,
    ]);
}

/**
 * The rendered landing page for whoever is acting in the current test.
 */
function landingpageMarkup(Meetup $meetup): string
{
    return Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->html();
}

/**
 * The markup of the element carrying `data-testid="upcoming-events"`, or null
 * when no element carries it.
 *
 * Parsed rather than sliced: "inside the section" is a containment question,
 * and a string search cannot answer it — the section has no closing marker a
 * test could find, and the page carries ~600 KB of Flux markup between the
 * section heading and the first event card.
 */
function upcomingEventsSectionMarkup(string $html): ?string
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $section = $document->querySelector('[data-testid="upcoming-events"]');

    return $section === null ? null : $document->saveHtml($section);
}

/**
 * The URL the "Neues Event erstellen" action points at, built through the same
 * helper the view uses so the country segment is resolved identically.
 */
function createEventUrl(Meetup $meetup): string
{
    return route_with_country('meetups.events.create', ['meetup' => $meetup]);
}

/*
|--------------------------------------------------------------------------
| 1. DOM order
|--------------------------------------------------------------------------
|
| Every offset is guarded with ->not->toBeFalse() BEFORE it is compared, and
| that guard is load-bearing: mb_strpos() returns false when the needle is
| missing, and PHP compares false against an int by casting the int to bool,
| so `false < 620616` is true. Without the guards, a page that lost the
| section altogether would satisfy every ordering assertion in this block.
*/

it('renders the upcoming events section before the about and contact blocks', function () {
    $meetup = meetupWithHeaderBlocks();
    MeetupEvent::factory()->for($meetup)->create(['start' => now()->addWeek()]);

    $html = landingpageMarkup($meetup);

    $section = mb_strpos($html, 'data-testid="upcoming-events"');
    // "Kontakt & Links" reaches the markup as "Kontakt &amp; Links" — the
    // heading goes through Blade's escaping, so the raw key never matches.
    $aboutUs = mb_strpos($html, __('Über uns'));
    $contact = mb_strpos($html, e(__('Kontakt & Links')));

    expect($section)->not->toBeFalse('no element carries data-testid="upcoming-events"')
        ->and($aboutUs)->not->toBeFalse('the "Über uns" block did not render')
        ->and($contact)->not->toBeFalse('the "Kontakt & Links" block did not render')
        ->and($section)->toBeLessThan($aboutUs)
        ->and($section)->toBeLessThan($contact);
});

it('renders the empty state before the about and contact blocks', function () {
    // Same claim for a meetup without events. Anchored on the empty state
    // rather than on the section, so it holds whether the empty state sits
    // inside `data-testid="upcoming-events"` or takes its place.
    $meetup = meetupWithHeaderBlocks();

    $html = landingpageMarkup($meetup);

    $emptyState = mb_strpos($html, 'data-testid="no-upcoming-events"');
    $aboutUs = mb_strpos($html, __('Über uns'));
    $contact = mb_strpos($html, e(__('Kontakt & Links')));

    expect($emptyState)->not->toBeFalse('no element carries data-testid="no-upcoming-events"')
        ->and($aboutUs)->not->toBeFalse('the "Über uns" block did not render')
        ->and($contact)->not->toBeFalse('the "Kontakt & Links" block did not render')
        ->and($emptyState)->toBeLessThan($aboutUs)
        ->and($emptyState)->toBeLessThan($contact);
});

/*
|--------------------------------------------------------------------------
| 2. The empty state, in both directions
|--------------------------------------------------------------------------
*/

it('shows the empty state for a meetup that has no event at all', function () {
    $meetup = meetupWithHeaderBlocks();

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->assertSeeHtml('data-testid="no-upcoming-events"');
});

it('shows no empty state for a meetup with an upcoming event', function () {
    $meetup = meetupWithHeaderBlocks();
    $event = MeetupEvent::factory()->for($meetup)->create(['start' => now()->addWeek()]);

    $html = landingpageMarkup($meetup);

    // The card is there AND the empty state is not. Asserting only the absence
    // would also pass on a page that renders neither.
    expect($html)
        ->toContain(route('meetups.landingpage-event', [
            'meetup' => $meetup->slug,
            'event' => $event->id,
            'country' => defaultCountrySegment(),
        ]))
        ->not->toContain('data-testid="no-upcoming-events"');
});

/*
|--------------------------------------------------------------------------
| 3. A past-only meetup counts as empty
|--------------------------------------------------------------------------
|
| The section filters on `start >= now()`. The two tests below pin both sides
| of that comparison, because the plausible ways to break it pull in opposite
| directions: dropping the filter lists yesterday's event again, and
| coarsening it to a whereDate()/today() comparison drops an event that starts
| later on the same day.
*/

it('counts a meetup whose only event lies in the past as having no upcoming event', function () {
    $meetup = meetupWithHeaderBlocks();
    $past = MeetupEvent::factory()->for($meetup)->create(['start' => now()->subWeek()]);

    $html = landingpageMarkup($meetup);

    expect($html)
        ->toContain('data-testid="no-upcoming-events"')
        // And the past event is not quietly listed above that empty state.
        ->not->toContain(route('meetups.landingpage-event', [
            'meetup' => $meetup->slug,
            'event' => $past->id,
            'country' => defaultCountrySegment(),
        ]));
});

it('still lists an event that starts later on the same day', function () {
    // The clock is frozen so "later today" stays "later today": at 09:00 an
    // event at 20:00 is upcoming, and a run started at 22:00 would otherwise
    // have pushed it into tomorrow and measured something else.
    $this->travelTo(Carbon::parse('2027-03-11 09:00:00'));

    $meetup = meetupWithHeaderBlocks();
    $event = MeetupEvent::factory()->for($meetup)->create([
        'start' => Carbon::parse('2027-03-11 20:00:00'),
    ]);

    $html = landingpageMarkup($meetup);

    expect($html)
        ->toContain(route('meetups.landingpage-event', [
            'meetup' => $meetup->slug,
            'event' => $event->id,
            'country' => defaultCountrySegment(),
        ]))
        ->not->toContain('data-testid="no-upcoming-events"');
});

/*
|--------------------------------------------------------------------------
| 4. The create-event action lives in the events section
|--------------------------------------------------------------------------
|
| Placement, not presence: the action used to sit in a header row of its own,
| and an assertSee() on its URL would not notice it going back there. So the
| number of create links inside the section is compared against the number on
| the whole page — that is what makes "and nowhere else" an assertion instead
| of a wish, and it stays honest about HOW MANY the section holds: the empty
| state may legitimately repeat the action next to its own copy.
*/

it('places the create-event action inside the upcoming events section for a leader', function () {
    $leader = actingAsUser();
    $meetup = meetupWithHeaderBlocks($leader);
    MeetupEvent::factory()->for($meetup)->create(['start' => now()->addWeek()]);

    $html = landingpageMarkup($meetup);
    $section = upcomingEventsSectionMarkup($html);
    $createUrl = createEventUrl($meetup);

    expect($section)->not->toBeNull('no element carries data-testid="upcoming-events"')
        ->and(substr_count($html, $createUrl))->toBeGreaterThan(0)
        ->and(substr_count((string) $section, $createUrl))->toBe(substr_count($html, $createUrl));
});

it('places the create-event action inside the events area for a leader of a meetup without events', function () {
    // A leader arriving at an empty meetup is the case #45 is really about:
    // the action has to be where the missing events are, not in a header row.
    $leader = actingAsUser();
    $meetup = meetupWithHeaderBlocks($leader);

    $html = landingpageMarkup($meetup);
    $section = upcomingEventsSectionMarkup($html);
    $createUrl = createEventUrl($meetup);

    $emptyState = mb_strpos($html, 'data-testid="no-upcoming-events"');
    $createAction = mb_strpos($html, $createUrl);
    $aboutUs = mb_strpos($html, __('Über uns'));

    expect($section)->not->toBeNull('no element carries data-testid="upcoming-events"')
        ->and($emptyState)->not->toBeFalse('no element carries data-testid="no-upcoming-events"')
        ->and($createAction)->not->toBeFalse('the create-event action did not render for the leader')
        ->and(substr_count((string) $section, $createUrl))->toBe(substr_count($html, $createUrl))
        ->and($aboutUs)->not->toBeFalse('the "Über uns" block did not render')
        ->and($createAction)->toBeLessThan($aboutUs);
});

it('shows no create-event action to a signed-in visitor who does not lead the meetup', function () {
    $meetup = meetupWithHeaderBlocks();
    MeetupEvent::factory()->for($meetup)->create(['start' => now()->addWeek()]);

    // Acting AFTER the meetup exists: its creator is made leader by a model
    // hook, so a user created first would be the one leading it.
    actingAsUser();

    $html = landingpageMarkup($meetup);

    // The section is still there — a visitor reads the event list, they just
    // get no action on it. Asserting only the absence of the URL would pass on
    // a page that failed to render the section at all.
    expect(upcomingEventsSectionMarkup($html))->not->toBeNull('no element carries data-testid="upcoming-events"')
        ->and(substr_count($html, createEventUrl($meetup)))->toBe(0);
});

it('shows no create-event action to a guest', function () {
    $meetup = meetupWithHeaderBlocks();
    MeetupEvent::factory()->for($meetup)->create(['start' => now()->addWeek()]);

    $html = landingpageMarkup($meetup);

    expect(upcomingEventsSectionMarkup($html))->not->toBeNull('no element carries data-testid="upcoming-events"')
        ->and(substr_count($html, createEventUrl($meetup)))->toBe(0);
});
