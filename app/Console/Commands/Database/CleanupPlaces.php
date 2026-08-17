<?php

namespace App\Console\Commands\Database;

use App\Models\City;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Removes cities nothing points at any more.
 *
 * This used to clean up venues first and cities second, because a city could only be
 * deleted once its last venue was gone. With the venue removed, events carry their city
 * directly and the two-step dance is over: one query answers whether a city is still in use.
 */
#[Signature('places:cleanup {--force : Actually delete instead of doing a dry-run}')]
#[Description('Delete cities without any meetups, course events or bitcoin events.')]
class CleanupPlaces extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force && ! $this->confirm('This permanently deletes cities on this database. Continue?', false)) {
            $this->warn('Aborted.');

            return Command::FAILURE;
        }

        $cities = City::query()
            ->whereDoesntHave('meetups')
            ->whereDoesntHave('courseEvents')
            ->whereDoesntHave('bitcoinEvents')
            ->get();

        if ($cities->isEmpty()) {
            $this->info('No unused city entries to clean up.');

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d unused city entry/entries.',
            $force ? 'Deleting' : '[DRY-RUN] Would delete',
            $cities->count(),
        ));

        if ($force) {
            // One ->delete() per model rather than a mass delete, so model events fire.
            $this->withProgressBar($cities, fn (City $city) => $city->delete());
            $this->newLine();
        }

        $this->newLine();
        $this->table(['Type', $force ? 'Deleted' : 'To delete'], [
            ['Cities', $cities->count()],
        ]);

        if (! $force) {
            $this->comment('Dry-run only. Re-run with --force to apply.');
        }

        return Command::SUCCESS;
    }
}
