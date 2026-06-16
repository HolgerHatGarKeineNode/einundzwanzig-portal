<?php

namespace App\Console\Commands\Database;

use App\Models\City;
use App\Models\Venue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Signature('places:cleanup {--force : Actually delete instead of doing a dry-run}')]
#[Description('Delete venues without any course or bitcoin events, then cities without any venues or meetups.')]
class CleanupPlaces extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force && ! $this->confirm('This permanently deletes venues and cities on this database. Continue?', false)) {
            $this->warn('Aborted.');

            return Command::FAILURE;
        }

        // Venues first: removing unused venues can make their city deletable in the same run.
        $venues = Venue::query()
            ->whereDoesntHave('courseEvents')
            ->whereDoesntHave('bitcoinEvents')
            ->get();

        $this->deleteAll('venue', $venues, $force);

        $cities = City::query()
            ->whereDoesntHave('venues')
            ->whereDoesntHave('meetups')
            ->get();

        $this->deleteAll('city', $cities, $force);

        $this->newLine();
        $this->table(['Type', $force ? 'Deleted' : 'To delete'], [
            ['Venues', $venues->count()],
            ['Cities', $cities->count()],
        ]);

        if (! $force) {
            $this->comment('Dry-run only. Re-run with --force to apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    private function deleteAll(string $label, $models, bool $force): void
    {
        if ($models->isEmpty()) {
            $this->info(sprintf('No unused %s entries to clean up.', $label));

            return;
        }

        $this->info(sprintf(
            '%s %d unused %s entry/entries.',
            $force ? 'Deleting' : '[DRY-RUN] Would delete',
            $models->count(),
            $label,
        ));

        if (! $force) {
            return;
        }

        // ->delete() per model (not a mass delete) so Venue media is removed via its delete hook.
        $this->withProgressBar($models, fn ($model) => $model->delete());
        $this->newLine();
    }
}
