<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Sidebar entries added by P4 of the #36-#45 gap closure
|--------------------------------------------------------------------------
|
| Two gaps, one file: /admin/webhooks (Issue #40) was reachable from nowhere —
| `grep -rn "admin.webhooks" resources/ app/ routes/` found only the route
| definition, so a board member had to know the URL. And a meetup organiser
| (Issue #45) had no direct way to their own meetup either.
|
| Anchor is the entry's own data-testid, not its label: the meetup name and
| the words "Webhook-Freigaben" also occur in page bodies, so a plain
| assertSee could pass on a page that renders the name somewhere else
| entirely. /de/services is used as the carrier page for the same reason —
| it lists services, never meetups.
|
| This sidebar IS the mobile navigation (flux:sidebar.toggle in
| components/layouts/app/sidebar.blade.php), so the same markup covers the
| mobile criterion of #45; there is no second nav to assert against.
|
*/

/** The names carried by the sidebar's own "my meetups" entries. */
function sidebarMeetupNames(string $html): array
{
    preg_match_all('/<a[^>]*data-testid="sidebar-my-meetup"[^>]*>(.*?)<\/a>/s', $html, $matches);

    return array_map(
        static fn (string $inner): string => trim(html_entity_decode(strip_tags($inner))),
        $matches[1]
    );
}

function hasSidebarTestid(string $html, string $testid): bool
{
    return preg_match('/<a[^>]*data-testid="'.preg_quote($testid, '/').'"/', $html) === 1;
}

function sidebarBoardUser(): User
{
    return User::factory()->create(['nostr' => config('einundzwanzig.board_members')[0]]);
}

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
});

/*
|--------------------------------------------------------------------------
| Issue #40 — the board-only entry
|--------------------------------------------------------------------------
*/

it('shows the webhook approval entry to a board member', function () {
    $html = $this->actingAs(sidebarBoardUser())->get('/de/services')->assertOk()->getContent();

    expect(hasSidebarTestid($html, 'sidebar-admin-webhooks'))->toBeTrue()
        ->and($html)->toContain('/de/admin/webhooks');
});

it('hides the webhook approval entry from an authenticated non-board user', function () {
    $html = $this->actingAs(User::factory()->create())->get('/de/services')->assertOk()->getContent();

    expect(hasSidebarTestid($html, 'sidebar-admin-webhooks'))->toBeFalse()
        ->and($html)->not->toContain('/de/admin/webhooks');
});

it('hides the webhook approval entry from a guest', function () {
    $html = $this->get('/de/services')->assertOk()->getContent();

    expect(hasSidebarTestid($html, 'sidebar-admin-webhooks'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Issue #45 — the organiser's own meetups
|--------------------------------------------------------------------------
|
| Meetup::ledBy() filters on `meetup_user.is_leader`, not on `created_by` —
| the same boundary meetups/index.blade.php:226 uses for the event
| affordances. A plain member of a meetup is therefore not an organiser here.
|
*/

it('lists a meetup the signed-in user leads', function () {
    $leader = User::factory()->create();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Bitcoin Meetup Musterstadt']);
    $meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    $html = $this->actingAs($leader)->get('/de/services')->assertOk()->getContent();

    expect(sidebarMeetupNames($html))->toBe(['Bitcoin Meetup Musterstadt'])
        ->and($html)->toContain('/de/meetup/'.$meetup->slug);
});

it('does not list a meetup the signed-in user does not lead', function () {
    $leader = User::factory()->create();
    $ownMeetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Eigenes Meetup']);
    $ownMeetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    // Member without the leader flag ...
    $memberOnly = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Nur Mitglied hier']);
    $memberOnly->users()->syncWithoutDetaching([$leader->id => ['is_leader' => false]]);

    // ... and a meetup this user has nothing to do with.
    Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Fremdes Meetup']);

    $html = $this->actingAs($leader)->get('/de/services')->assertOk()->getContent();

    expect(sidebarMeetupNames($html))->toBe(['Eigenes Meetup']);
});

it('lists nothing for a guest and runs no meetup lookup at all', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Bitcoin Meetup Musterstadt']);
    $meetup->users()->syncWithoutDetaching([User::factory()->create()->id => ['is_leader' => true]]);

    $html = $this->get('/de/services')->assertOk()->getContent();

    expect(sidebarMeetupNames($html))->toBe([]);
});

it('links each entry to the country of that meetup, not the country of the current page', function () {
    $switzerland = Country::factory()->create(['code' => 'ch']);
    $bern = City::factory()->create(['country_id' => $switzerland->id]);

    $leader = User::factory()->create();
    $meetup = Meetup::factory()->create(['city_id' => $bern->id, 'name' => 'Bitcoin Meetup Bern']);
    $meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    $html = $this->actingAs($leader)->get('/de/services')->assertOk()->getContent();

    expect($html)->toContain('/ch/meetup/'.$meetup->slug)
        ->and($html)->not->toContain('/de/meetup/'.$meetup->slug);
});

/*
|--------------------------------------------------------------------------
| Query cost
|--------------------------------------------------------------------------
|
| The sidebar renders on every page, so a query per entry would be a
| regression across the whole app (the shape of
| tests/Feature/Meetups/AdministrationFormPerformanceTest.php's second test).
| Measured: 3 queries for the list — meetups, then the eager-loaded cities
| and countries — and the same 3 whether one meetup is led or six.
|
*/

it('adds a constant number of queries no matter how many meetups are led', function () {
    // A fresh leader per measurement instead of deleting rows in between: the
    // meetups of the previous round stay, they are simply not led by this user.
    $countFor = function (int $meetupCount): int {
        $leader = User::factory()->create();

        for ($i = 0; $i < $meetupCount; $i++) {
            // `meetups_lower_name_unique` spans the whole table, so the name has to
            // stay unique across both measurement rounds too.
            $meetup = Meetup::factory()->create([
                'city_id' => $this->city->id,
                'name' => "Meetup {$meetupCount}-{$i}",
            ]);
            $meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $html = $this->actingAs($leader)->get('/de/services')->assertOk()->getContent();
        expect(sidebarMeetupNames($html))->toHaveCount($meetupCount);

        return collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, 'meetup_user')
            || str_contains($sql, 'from "cities"')
            || str_contains($sql, 'from "countries"'))->count();
    };

    $one = $countFor(1);
    $six = $countFor(6);

    expect($six)->toBe($one);
});
