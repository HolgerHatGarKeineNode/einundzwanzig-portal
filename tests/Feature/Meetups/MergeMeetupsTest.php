<?php

use App\Actions\MergeMeetups;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->merger = app(MergeMeetups::class);
});

function mergeTestMeetup(int $cityId, array $attributes = []): Meetup
{
    return Meetup::factory()->create(array_merge([
        'city_id' => $cityId,
        'created_by' => User::factory()->create(['nostr' => null])->id,
    ], $attributes));
}

it('moves the events across before the loser is deleted', function () {
    $survivor = mergeTestMeetup($this->city->id);
    $loser = mergeTestMeetup($this->city->id);
    $events = MeetupEvent::factory()->count(3)->create(['meetup_id' => $loser->id]);

    $result = $this->merger->handle($survivor, $loser);

    // meetup_events.meetup_id ist cascadeOnDelete — ohne den Umhang waeren die
    // drei Termine mit dem Verlierer verschwunden statt umzuziehen.
    expect(Meetup::find($loser->id))->toBeNull();
    expect($result['moved']['meetup_events'])->toBe(3);
    foreach ($events as $event) {
        expect($event->fresh()->meetup_id)->toBe($survivor->id);
    }
});

it('never demotes a leader who leads both meetups', function () {
    $survivor = mergeTestMeetup($this->city->id);
    $loser = mergeTestMeetup($this->city->id);
    $person = User::factory()->create(['nostr' => null]);

    $survivor->addMember($person);          // beim Ueberlebenden nur Mitglied
    $loser->promoteLeader($person);         // beim Verlierer Leader

    $this->merger->handle($survivor, $loser);

    expect($survivor->fresh()->isLeader($person))->toBeTrue();
});

it('does not list the same person twice on the survivor', function () {
    $survivor = mergeTestMeetup($this->city->id);
    $loser = mergeTestMeetup($this->city->id);
    $person = User::factory()->create(['nostr' => 'npub1'.str_repeat('a', 58)]);

    $survivor->promoteLeader($person);
    $loser->promoteLeader($person);

    $this->merger->handle($survivor, $loser);

    // meetup_user hat weder Primaerschluessel noch unique auf (meetup_id, user_id);
    // ein blindes Umhaengen wuerde die Person doppelt fuehren.
    $zeilen = DB::table('meetup_user')
        ->where('meetup_id', $survivor->id)
        ->where('user_id', $person->id)
        ->count();

    expect($zeilen)->toBe(1);
    expect($survivor->fresh()->leaders()->pluck('id')->all())->toContain($person->id);
});

it('takes over empty fields but never overwrites filled ones', function () {
    $survivor = mergeTestMeetup($this->city->id, ['intro' => null, 'webpage' => 'https://bleibt.example']);
    $loser = mergeTestMeetup($this->city->id, ['intro' => 'Text vom Verlierer', 'webpage' => 'https://verliert.example']);

    $result = $this->merger->handle($survivor, $loser);

    $survivor->refresh();
    expect($survivor->intro)->toBe('Text vom Verlierer');
    expect($survivor->webpage)->toBe('https://bleibt.example');
    expect($result['filled'])->toContain('intro')->not->toContain('webpage');
});

it('keeps a snapshot of the deleted meetup', function () {
    $survivor = mergeTestMeetup($this->city->id);
    $loser = mergeTestMeetup($this->city->id, ['name' => 'Verschwundenes Meetup']);

    $result = $this->merger->handle($survivor, $loser);

    expect($result['snapshot']['name'])->toBe('Verschwundenes Meetup');
    expect($result['snapshot']['id'])->toBe($loser->id);
});

it('refuses to merge a meetup into itself', function () {
    $meetup = mergeTestMeetup($this->city->id);

    $this->merger->handle($meetup, $meetup);
})->throws(InvalidArgumentException::class);
