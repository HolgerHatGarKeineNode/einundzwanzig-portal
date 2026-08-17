<?php

namespace App\Console\Commands;

use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repairs tags whose translations were written as JSON strings instead of arrays.
 *
 * TagFactory used to pass json_encode(['en' => …, 'de' => …]) for `name` and `slug`.
 * Spatie's Tag::setAttribute() sends any non-array value for a translatable key
 * through setTranslation() with the *current* locale, so the whole JSON string was
 * stored as a single language's value:
 *
 *   name  {"de": "{\"en\":\"sint-2756\",\"de\":\"sint-2756\"}"}
 *   slug  {"de": "ensint-2756desint-2756"}
 *
 * The slug is the giveaway — the generator slugified the JSON syntax along with the
 * content. This command unwraps the nested payload and regenerates the slugs, which
 * cannot heal on their own because the model deliberately never overwrites an
 * existing slug.
 */
class RepairTagTranslations extends Command
{
    protected $signature = 'tags:repair-translations
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Unwrap doubly-encoded tag translations and regenerate their slugs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $repaired = 0;
        $skipped = 0;

        foreach (Tag::query()->cursor() as $tag) {
            $raw = $tag->getAttributes();
            $changes = [];

            foreach (['name', 'description'] as $field) {
                $unwrapped = $this->unwrap($raw[$field] ?? null);

                if ($unwrapped !== null) {
                    $changes[$field] = $unwrapped;
                }
            }

            if ($changes === []) {
                $skipped++;

                continue;
            }

            /*
             * Only regenerate slugs when the *name* was the broken part. If a future
             * run finds nothing wrong but a nested `description`, the existing slugs
             * are still correct and must stay — rewriting them from the current name
             * would break exactly the stability guarantee that Tag::bootHasSlug()
             * exists to provide.
             */
            if (isset($changes['name'])) {
                $changes['slug'] = $this->slugsFor($changes['name']);
            }

            $this->line(sprintf(
                '  #%d  %s  ->  %s',
                $tag->id,
                Str::limit((string) ($raw['name'] ?? ''), 48),
                json_encode($changes['name'] ?? [], JSON_UNESCAPED_UNICODE)
            ));

            if (! $dryRun) {
                DB::table('tags')
                    ->where('id', $tag->id)
                    ->update(array_map(
                        static fn (array $value): string => json_encode($value, JSON_UNESCAPED_UNICODE),
                        $changes
                    ));
            }

            $repaired++;
        }

        $this->newLine();
        $this->info($dryRun
            ? "Would repair {$repaired} tag(s); {$skipped} already fine."
            : "Repaired {$repaired} tag(s); {$skipped} already fine.");

        return self::SUCCESS;
    }

    /**
     * Return the unwrapped translations if this column holds the nested wreckage,
     * or null if it is already well-formed.
     *
     * A healthy column is a locale map of plain strings. The broken shape is a locale
     * map whose value is itself a JSON object of locales — that inner object is the
     * translation set that was meant to be stored.
     *
     * @return array<string, string>|null
     */
    private function unwrap(?string $rawValue): ?array
    {
        $outer = $this->decode($rawValue);

        if ($outer === null) {
            return null;
        }

        $result = [];
        $foundNested = false;

        foreach ($outer as $locale => $value) {
            $inner = is_string($value) ? $this->decode($value) : null;

            if ($inner === null) {
                $result[$locale] = $value;

                continue;
            }

            $foundNested = true;

            foreach ($inner as $innerLocale => $innerValue) {
                $result[$innerLocale] ??= $innerValue;
            }
        }

        return $foundNested ? $result : null;
    }

    /**
     * @return array<string, string>|null
     */
    private function decode(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        foreach (array_keys($decoded) as $key) {
            if (! is_string($key)) {
                return null;
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, string>
     */
    private function slugsFor(array $names): array
    {
        $slugger = config('tags.slugger') ?? '\Illuminate\Support\Str::slug';

        return array_map(
            static fn (string $name): string => call_user_func($slugger, $name),
            $names
        );
    }
}
