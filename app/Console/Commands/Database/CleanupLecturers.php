<?php

namespace App\Console\Commands\Database;

use App\Models\Lecturer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('lecturers:cleanup {--force : Actually delete instead of doing a dry-run}')]
#[Description('Delete lecturers without any courses or course events. Their library items and media are merged into the "Einundzwanzig" lecturer first.')]
class CleanupLecturers extends Command
{
    private const MERGE_TARGET_NAME = 'Einundzwanzig';

    public function handle(): int
    {
        $target = Lecturer::query()->where('name', self::MERGE_TARGET_NAME)->first();

        if (! $target) {
            $this->error(sprintf('Merge target lecturer "%s" not found. Aborting.', self::MERGE_TARGET_NAME));

            return Command::FAILURE;
        }

        $candidates = Lecturer::query()
            ->whereKeyNot($target->getKey())
            ->whereDoesntHave('courses')
            ->whereDoesntHave('coursesEvents')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No empty lecturers to clean up.');

            return Command::SUCCESS;
        }

        $force = (bool) $this->option('force');

        $this->info(sprintf(
            '%s %d empty lecturer(s). Library items & media will be merged into "%s" (#%d).',
            $force ? 'Deleting' : '[DRY-RUN] Would delete',
            $candidates->count(),
            $target->name,
            $target->getKey(),
        ));

        if ($force && ! $this->confirm('This permanently deletes lecturers on this database. Continue?', false)) {
            $this->warn('Aborted.');

            return Command::FAILURE;
        }

        $stats = ['deleted' => 0, 'libraryItems' => 0, 'media' => 0];

        $this->withProgressBar($candidates, function (Lecturer $lecturer) use ($target, $force, &$stats): void {
            // Use ->count() (query) instead of loading the relation, so the
            // media delete-hook on $lecturer->delete() sees an empty set after reassignment.
            $libraryItems = $lecturer->libraryItems()->count();
            $media = $lecturer->media()->count();

            $stats['libraryItems'] += $libraryItems;
            $stats['media'] += $media;
            $stats['deleted']++;

            if (! $force) {
                return;
            }

            DB::transaction(function () use ($lecturer, $target): void {
                $lecturer->libraryItems()->update(['lecturer_id' => $target->getKey()]);
                // ponytail: reassigning model_id keeps files & model_type (both are Lecturer) intact.
                // Ceiling: merged 'avatar' media land in the target's singleFile collection, so a later
                // avatar upload on the target will prune them. Move them to 'images' if that matters.
                $lecturer->media()->update(['model_id' => $target->getKey()]);
                $lecturer->delete();
            });
        });

        $this->newLine(2);
        $this->table(['Metric', 'Count'], [
            [$force ? 'Lecturers deleted' : 'Lecturers to delete', $stats['deleted']],
            [$force ? 'Library items merged' : 'Library items to merge', $stats['libraryItems']],
            [$force ? 'Media merged' : 'Media to merge', $stats['media']],
        ]);

        if (! $force) {
            $this->newLine();
            $this->comment('Dry-run only. Re-run with --force to apply.');
        }

        return Command::SUCCESS;
    }
}
