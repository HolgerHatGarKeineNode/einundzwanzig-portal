<?php

namespace App\Console\Commands;

use App\Support\SushiCache;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Throwable;

/**
 * Builds the on-disk cache of every Sushi-backed model before traffic can
 * reach a new release (Issue #139).
 *
 * A zero-downtime deploy unpacks each release into a fresh directory, so every
 * Sushi model file gets a new mtime while storage/ stays a shared symlink —
 * which means every deploy invalidates the cache and the rebuild happens
 * lazily, inside whichever web request arrives first. That is the race that
 * 500'd eight requests in 83 seconds on 2026-09-06.
 *
 * This command is wired into composer.json's post-autoload-dump, next to
 * package:discover: it runs during `composer install` inside the new release,
 * after the storage symlink exists and before the current symlink flips.
 *
 * It never fails. A warm-up problem is reported and logged, and the exit code
 * stays 0 — breaking `composer install` over a cache file would turn a slow
 * first request into a failed deploy.
 */
class WarmSushiCaches extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sushi:warm {model?* : Fully qualified Sushi model classes; defaults to every discovered one}';

    /**
     * @var string
     */
    protected $description = 'Build and verify the SQLite cache of every Sushi-backed model';

    public function handle(): int
    {
        try {
            /** @var array<int, class-string> $models */
            $models = $this->argument('model') ?: SushiCache::discover();
        } catch (Throwable $exception) {
            $this->warn('Sushi model discovery failed: '.$exception->getMessage());

            return Command::SUCCESS;
        }

        if ($models === []) {
            $this->info('No Sushi-backed models found.');

            return Command::SUCCESS;
        }

        foreach ($models as $model) {
            $this->warm($model);
        }

        return Command::SUCCESS;
    }

    /**
     * @param  class-string  $model
     */
    protected function warm(string $model): void
    {
        try {
            if (! class_exists($model)) {
                $this->warn($model.': no such class.');

                return;
            }

            $reflection = new ReflectionClass($model);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                $this->warn($model.': not an Eloquent model.');

                return;
            }

            if (! SushiCache::isSushiModel($model)) {
                $this->warn($model.': does not use the Sushi trait.');

                return;
            }

            // Deliberately without the constructor: instantiating the model
            // would boot it, and booting is what reads the cache file. The
            // cache has to be sound before that happens.
            $blueprint = $reflection->newInstanceWithoutConstructor();

            $status = SushiCache::ensureFresh($blueprint);
            $expected = count($blueprint->getRows());

            // Boot the model for real and read the table back, so what is
            // reported is what a web request would get, not what this command
            // believes it wrote.
            $counted = $model::query()->count();

            if ($counted !== $expected) {
                $this->warn(sprintf('%s: %d row(s) in the cache, %d expected.', $model, $counted, $expected));

                return;
            }

            $this->line(sprintf('%s: %d row(s) (%s).', $model, $counted, $status));
        } catch (Throwable $exception) {
            $this->warn($model.': warm-up failed — '.$exception->getMessage());
        }
    }
}
