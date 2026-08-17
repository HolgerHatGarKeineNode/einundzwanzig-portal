<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Seeds the curated tag vocabulary from database/seeders/data/tags.php.
 *
 * Idempotent: a tag is matched by its type plus its German name, so re-running
 * updates the existing row instead of creating a second one. That matters because
 * the production set already contains exactly the duplicates this seeder is meant
 * to replace — a blind insert would deepen the problem it exists to solve.
 */
class TagSeeder extends Seeder
{
    public function run(): void
    {
        $vocabulary = require database_path('seeders/data/tags.php');
        $locales = config('einundzwanzig.tag_locales');

        foreach ($vocabulary as $type => $entries) {
            foreach ($entries as $entry) {
                $names = $this->namesFor($entry['name'], $locales);

                $tag = $this->findExisting($type, $names)
                    ?? new Tag(['type' => $type]);

                foreach ($names as $locale => $name) {
                    $tag->setTranslation('name', $locale, $name);
                }

                $tag->type = $type;
                $tag->icon = $entry['icon'] ?? 'tag';
                $tag->featured = (bool) ($entry['featured'] ?? false);
                $tag->approved_at ??= now();

                $tag->save();
            }
        }
    }

    /**
     * Expand the shorthand: a plain string is a proper noun and is used verbatim
     * in every locale, so "Nostr" stays "Nostr" and is still findable in each one.
     *
     * @param  string|array<string, string>  $name
     * @return array<string, string>
     */
    private function namesFor(string|array $name, array $locales): array
    {
        if (is_string($name)) {
            return array_fill_keys($locales, $name);
        }

        return $name;
    }

    /**
     * Match on type plus German name — the source language of the vocabulary.
     *
     * Loads the type's tags and compares in PHP rather than querying the JSON
     * column, which keeps the seeder portable across SQLite and MySQL. At this
     * vocabulary size (under a hundred rows) the cost is irrelevant.
     *
     * @param  array<string, string>  $names
     */
    private function findExisting(string $type, array $names): ?Tag
    {
        $german = $names['de'] ?? reset($names);

        return Tag::query()
            ->where('type', $type)
            ->get()
            ->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de', false) === $german);
    }
}
