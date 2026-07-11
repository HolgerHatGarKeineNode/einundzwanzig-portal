<?php

declare(strict_types=1);

use App\Actions\MergeUserAccounts;
use App\Models\City;
use App\Models\Meetup;
use App\Models\MergeAudit;
use App\Models\ProjectProposal;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseMissing;

it('moves meetup leadership from the loser to the survivor and deletes the loser', function () {
    $survivor = User::factory()->create(['nostr' => 'npub1survivor', 'public_key' => null]);
    $loser = User::factory()->create(['nostr' => null, 'public_key' => 'deadbeef'.str_repeat('0', 56)]);

    $meetup = Meetup::factory()->create(['created_by' => $loser->id]);

    expect($meetup->isLeader($loser))->toBeTrue();

    $audit = app(MergeUserAccounts::class)->handle(
        survivor: $survivor,
        loser: $loser,
        verifiedIdentity: 'npub1survivor',
        direction: 'lightning_into_nostr',
    );

    $meetup->refresh();
    $survivor->refresh();

    expect($meetup->created_by)->toBe($survivor->id)
        ->and($meetup->isLeader($survivor))->toBeTrue()
        ->and($survivor->public_key)->toBe('deadbeef'.str_repeat('0', 56));

    assertDatabaseMissing('users', ['id' => $loser->id]);
    expect(MergeAudit::whereKey($audit->id)->exists())->toBeTrue();
});

it('OR-merges the leader flag when both accounts are members of the same meetup', function () {
    $survivor = User::factory()->create();
    $loser = User::factory()->create();

    $meetup = Meetup::factory()->create();
    $meetup->addMember($survivor);   // is_leader = false
    $meetup->promoteLeader($loser);  // is_leader = true

    app(MergeUserAccounts::class)->handle($survivor, $loser, 'x', 'link');

    $meetup->refresh();

    expect($meetup->isLeader($survivor))->toBeTrue()
        ->and(DB::table('meetup_user')->where('meetup_id', $meetup->id)->where('user_id', $loser->id)->count())->toBe(0);
});

it('repoints created_by records and votes without loss', function () {
    $survivor = User::factory()->create();
    $loser = User::factory()->create();

    $city = City::factory()->create(['created_by' => $loser->id]);
    $vote = Vote::factory()->create(['user_id' => $loser->id, 'created_by' => $loser->id]);

    app(MergeUserAccounts::class)->handle($survivor, $loser, 'x', 'link');

    expect($city->fresh()->created_by)->toBe($survivor->id)
        ->and($vote->fresh()->user_id)->toBe($survivor->id);
});

it('rewrites and dedupes RSVP json markers', function () {
    $survivor = User::factory()->create();
    $loser = User::factory()->create();
    $meetup = Meetup::factory()->create();

    // Different display names on the two accounts — the dedupe must key on the
    // id, not the whole "id_n|name" string, or the survivor is listed twice.
    $event = $meetup->meetupEvents()->create([
        'start' => now()->addWeek(),
        'created_by' => $survivor->id,
        'attendees' => ["id_{$survivor->id}|Alice", "id_{$loser->id}|Bob"],
        'might_attendees' => [],
    ]);

    app(MergeUserAccounts::class)->handle($survivor, $loser, 'x', 'link');

    expect($event->fresh()->attendees)->toBe(["id_{$survivor->id}|Alice"]);
});

it('dedupes votes on the same proposal instead of double-counting the tally', function () {
    $survivor = User::factory()->create();
    $loser = User::factory()->create();
    $proposal = ProjectProposal::factory()->create();

    Vote::factory()->create(['user_id' => $survivor->id, 'created_by' => $survivor->id, 'project_proposal_id' => $proposal->id, 'value' => 1]);
    Vote::factory()->create(['user_id' => $loser->id, 'created_by' => $loser->id, 'project_proposal_id' => $proposal->id, 'value' => 1]);

    app(MergeUserAccounts::class)->handle($survivor, $loser, 'x', 'link');

    expect(Vote::where('user_id', $survivor->id)->where('project_proposal_id', $proposal->id)->count())->toBe(1);
});

it('does not overwrite survivor identity fields that are already set', function () {
    $survivor = User::factory()->create(['nostr' => 'npub1keep', 'public_key' => null]);
    $loser = User::factory()->create(['nostr' => 'npub1loser', 'public_key' => 'ff'.str_repeat('0', 62)]);

    app(MergeUserAccounts::class)->handle($survivor, $loser, 'npub1keep', 'lightning_into_nostr');

    $survivor->refresh();

    expect($survivor->nostr)->toBe('npub1keep')
        ->and($survivor->public_key)->toBe('ff'.str_repeat('0', 62));
});
