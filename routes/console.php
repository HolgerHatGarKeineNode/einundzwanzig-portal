<?php

use App\Console\Commands\Database\CleanupLoginKeys;
use App\Console\Commands\Database\PruneApiChanges;
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

/*
 * Bewusst NACH dem Aktivitaets-Lauf um 03:30: der schreibt selbst in `api_changes`
 * (siehe Meetup::recalculateActivity), und die beiden sollen nicht gleichzeitig auf
 * derselben Tabelle arbeiten. Die Frist steht in config/einundzwanzig.php und ist
 * zugleich die Reichweite, ueber die ein Konsument per /api/changes nachziehen kann.
 */
Schedule::command(PruneApiChanges::class)->dailyAt('04:00');
