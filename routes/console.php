<?php

use App\Console\Commands\Database\CleanupLoginKeys;
use App\Console\Commands\Feed\ReadAndSyncPodcastFeeds;
use App\Console\Commands\OpenBooks\SyncOpenBooks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nova\Trix\PruneStaleAttachments;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::call(new PruneStaleAttachments)
    ->daily();
Schedule::command(SyncOpenBooks::class)
    ->dailyAt('04:00');
Schedule::command(ReadAndSyncPodcastFeeds::class)
    ->dailyAt('04:30');
Schedule::command(CleanupLoginKeys::class)
    ->everyFifteenMinutes();
