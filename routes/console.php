<?php

use App\Console\Commands\Database\CleanupLoginKeys;
use App\Console\Commands\Database\UpdateMeetupActivity;
use App\Console\Commands\Nostr\PublishUnpublishedItems;

Schedule::command(CleanupLoginKeys::class)->everyFifteenMinutes();

Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'MeetupEvent',
])->hourly();

Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'Meetup',
])->dailyAt('18:00');

Schedule::command(UpdateMeetupActivity::class)->dailyAt('03:30');
