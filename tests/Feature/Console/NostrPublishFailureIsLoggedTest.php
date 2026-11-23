<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Monolog\Handler\TestHandler;

/*
 * Nightwatch issues #44 and #47: a failed `nostr:publish` run reached Nightwatch only as
 * "Scheduled command [...] failed with exit code [1]". The reason was printed to the
 * console of a scheduled run and lost. These tests pin that the reason is logged, and
 * that it reaches the `nightwatch` channel whether or not LOG_STACK contains it.
 */

function failureLogMeetupEvent(): MeetupEvent
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
    ]);

    return MeetupEvent::factory()->create([
        'meetup_id' => Meetup::factory()->create(['city_id' => $city->id])->id,
        'start' => now()->addDay(),
        'nostr_status' => null,
    ]);
}

function testHandlerOf(string $channel): TestHandler
{
    return Log::channel($channel)->getLogger()->getHandlers()[0];
}

beforeEach(function () {
    config([
        'logging.channels.app-file' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        'logging.channels.nightwatch' => ['driver' => 'monolog', 'handler' => TestHandler::class],
    ]);

    // A channel resolved before this config change would keep its old handlers.
    foreach (['stack', 'app-file', 'nightwatch'] as $channel) {
        Log::forgetChannel($channel);
    }

    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'relay refused the note', exitCode: 1),
    ]);
});

it('logs the reason of a refused publish to the default log and to nightwatch', function () {
    config([
        'logging.default' => 'stack',
        'logging.channels.stack' => ['driver' => 'stack', 'channels' => ['app-file']],
    ]);
    $meetupEvent = failureLogMeetupEvent();

    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])->assertExitCode(1);

    foreach (['app-file', 'nightwatch'] as $channel) {
        $records = testHandlerOf($channel)->getRecords();

        expect($records)->toHaveCount(1)
            ->and($records[0]->level->getName())->toBe('WARNING')
            ->and($records[0]->message)->toBe('Failed to publish for MeetupEvent: relay refused the note')
            ->and($records[0]->context)->toMatchArray([
                'command' => 'nostr:publish',
                'model' => MeetupEvent::class,
                'id' => $meetupEvent->id,
            ]);
    }
});

it('logs to nightwatch only once when the default stack already contains it', function () {
    config([
        'logging.default' => 'stack',
        'logging.channels.stack' => ['driver' => 'stack', 'channels' => ['app-file', 'nightwatch']],
    ]);
    failureLogMeetupEvent();

    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])->assertExitCode(1);

    expect(testHandlerOf('nightwatch')->getRecords())->toHaveCount(1)
        ->and(testHandlerOf('app-file')->getRecords())->toHaveCount(1);
});
