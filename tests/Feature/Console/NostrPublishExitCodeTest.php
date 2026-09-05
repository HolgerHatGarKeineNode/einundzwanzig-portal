<?php

use App\Console\Commands\Nostr\PublishUnpublishedItems;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;

/*
 * Issue #84: `nostr:publish` reported every real failure with exit code 0.
 *
 * The command runs from routes/console.php hourly (MeetupEvent) and daily at 18:00
 * (Meetup). Laravel's ScheduleRunCommand::runEvent is the only thing watching, and it
 * watches exactly one number: a foreground event whose exit code is non-zero raises
 * "Scheduled command [...] failed with exit code [N]", dispatches ScheduledTaskFailed
 * and reports the exception. At exit 0 none of that happens — a publish that fails on
 * every run is recorded as a success, and the only trace is a log line nobody reads.
 *
 * These tests pin one exit code per branch, in both directions: the failures must stay
 * non-zero, and the two success paths must stay 0, because an idle queue is the normal
 * state of an hourly run and alarming on it would be worse than the defect being fixed.
 */

function exitCodeTestMeetup(array $attributes = []): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
    ]);

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $attributes));
}

function exitCodeTestMeetupEvent(): MeetupEvent
{
    return MeetupEvent::factory()->create([
        'meetup_id' => exitCodeTestMeetup()->id,
        'start' => now()->addDay(),
        // MeetupEventFactory fills nostr_status from NostrHelper::fakeNostrEventStatus(),
        // which returns a status in 10 % of calls — that would take the record out of the
        // command's `whereNull` gate and flake these tests one run in ten.
        'nostr_status' => null,
    ]);
}

it('exits INVALID when --model names a model the command cannot query', function () {
    $this->artisan('nostr:publish', ['--model' => 'Sprint'])
        ->expectsOutputToContain('Unsupported model: Sprint')
        ->assertExitCode(2);
});

it('exits INVALID when --model is missing entirely', function () {
    $this->artisan('nostr:publish')
        ->expectsOutputToContain('Unsupported model')
        ->assertExitCode(2);
});

/*
 * The seam is deliberate. NostrTrait::getText returns null only through its
 * `default` arm — a model that is none of Course, CourseEvent, Meetup, MeetupEvent —
 * and the command's own match cannot select any other model, so today nothing can
 * reach this branch. An empty translation cannot either: Translator::get returns
 * `$line ?: $key`, so a blank line falls back to the key and is never falsy.
 *
 * The branch is therefore defence in depth, and the pin is what keeps it that way:
 * whoever next adds a model arm to the command, or a text arm to the trait, cannot
 * make "no text generated" exit 0 again without this test going red.
 */
it('exits FAILURE when no text could be generated for the record', function () {
    exitCodeTestMeetupEvent();
    Process::fake();

    $this->app->bind(PublishUnpublishedItems::class, fn () => new class extends PublishUnpublishedItems
    {
        public function getText(Model $model, string $countryCode): ?string
        {
            return null;
        }
    });

    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('No text generated for MeetupEvent')
        ->assertExitCode(1);

    Process::assertNothingRan();
});

it('exits FAILURE when the relay refuses the publish', function () {
    exitCodeTestMeetupEvent();

    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'relay refused the note', exitCode: 1),
    ]);

    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('Failed to publish for MeetupEvent: relay refused the note')
        ->assertExitCode(1);
});

it('stays SUCCESS when the publish goes through', function () {
    $meetupEvent = exitCodeTestMeetupEvent();

    Process::fake([
        '*' => Process::result(output: 'note1'.str_repeat('a', 58)),
    ]);

    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('Published successfully for MeetupEvent')
        ->assertExitCode(0);

    expect($meetupEvent->fresh()->nostr_status)->toBe('note1'.str_repeat('a', 58));
});

it('stays SUCCESS on an idle run with nothing to publish', function () {
    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('No unpublished items for model: MeetupEvent')
        ->assertExitCode(0);

    $this->artisan('nostr:publish', ['--model' => 'Meetup'])
        ->expectsOutputToContain('No unpublished items for model: Meetup')
        ->assertExitCode(0);
});
