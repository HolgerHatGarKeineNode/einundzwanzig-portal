<?php

namespace App\Console\Commands;

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Console\Command;

/**
 * Fills empty tag descriptions from the curated vocabulary — the production
 * companion to the seeder (issue #149).
 *
 * `db:seed` does not run in production contexts without thought, and operators
 * should not have to remember a class name for a maintenance task. The command
 * runs the same idempotent TagSeeder a fresh environment gets: a locale
 * receives its guidance text only while it carries none, so anything an editor
 * has written in tags.moderation survives. The vocabulary also restates
 * `featured`, which is how the Bitcoin tag leaves the picker's resting list.
 */
class SeedTagGuidance extends Command
{
    protected $signature = 'tags:seed-guidance';

    protected $description = 'Fill empty tag descriptions from the curated vocabulary (issue #149)';

    public function handle(): int
    {
        $described = fn (): int => Tag::query()
            ->get()
            ->filter(fn (Tag $tag): bool => filled($tag->getTranslation('description', 'de', false)))
            ->count();

        $before = $described();

        $this->call('db:seed', ['--class' => TagSeeder::class, '--force' => true]);

        $after = $described();

        $this->info(sprintf(
            'Tags with a German description: %d before, %d after. Empty locales were filled; existing texts and slugs were left alone.',
            $before,
            $after,
        ));

        return self::SUCCESS;
    }
}
