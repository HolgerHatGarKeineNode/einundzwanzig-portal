<?php

use App\Console\Commands\Database\CleanupLoginKeys;
use App\Console\Commands\Feed\ReadAndSyncPodcastFeeds;
use App\Console\Commands\MempoolSpace\CacheRecommendedFees;
use App\Console\Commands\Nostr\PublishUnpublishedItems;
use App\Console\Commands\OpenBooks\SyncOpenBooks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nova\Trix\PruneStaleAttachments;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command(CacheRecommendedFees::class)->everyFourHours();
Schedule::call(new PruneStaleAttachments)
    ->daily();
Schedule::command(SyncOpenBooks::class)
    ->hourlyAt(15);
Schedule::command(ReadAndSyncPodcastFeeds::class)
    ->dailyAt('04:30');
Schedule::command(CleanupLoginKeys::class)
    ->everyFifteenMinutes();
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'BitcoinEvent',
])
    ->dailyAt('08:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'CourseEvent',
])
    ->dailyAt('09:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'MeetupEvent',
])
    ->dailyAt('10:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'Meetup',
])
    ->dailyAt('11:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'OrangePill',
])
    ->dailyAt('12:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'LibraryItem',
])
    ->dailyAt('13:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'BitcoinEvent',
])
    ->dailyAt('14:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'CourseEvent',
])
    ->dailyAt('15:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'MeetupEvent',
])
    ->dailyAt('16:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'Meetup',
])
    ->dailyAt('17:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'OrangePill',
])
    ->dailyAt('18:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'LibraryItem',
])
    ->dailyAt('19:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'BitcoinEvent',
])
    ->dailyAt('20:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'LibraryItem',
])
    ->dailyAt('21:00');
Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'LibraryItem',
])
    ->dailyAt('22:00');
