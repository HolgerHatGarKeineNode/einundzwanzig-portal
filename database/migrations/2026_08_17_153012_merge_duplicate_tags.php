<?php

use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapses the duplicate tags found in the production set on 2026-08-17 and renames
 * the entries whose spelling the curated vocabulary normalises.
 *
 * Why this exists: `Philosophie`, `Etatismus`, `Kryptografie`, `News` and `Bitcoin`
 * each existed TWICE — once German, once English — as separate rows rather than as one
 * translated row. Plus `#Bindle ` next to `Bindle`, the typo `Immmobilien`, `Reise`
 * next to `Travel`, `Privacy` next to `Privatsphäre`, `PlebRapCash` next to `PlebRap`,
 * and four different names for the same thesis category.
 *
 * The renames matter for what runs next: TagSeeder matches an existing tag by its
 * GERMAN name. Without them it would not recognise `Anlage` as `Geldanlage` and would
 * create a second row instead of translating the first.
 *
 * Matching is by (type, locale, name) rather than by primary key, so this runs against
 * any environment — production IDs mean nothing locally. Everything is skipped silently
 * when a tag is absent, which makes the migration idempotent and safe on a fresh database.
 *
 * NOT REVERSIBLE. Merged rows are deleted; `down()` cannot resurrect them or tell which
 * taggables used to point where. Take a database backup before running this on live data.
 */
return new class extends Migration
{
    /**
     * Tags that mean the same thing. `keep` survives, `fold` is merged into it.
     * Each is identified by locale => name, plus the type they live in.
     *
     * @var array<int, array{type: string, keep: array<string, string>, fold: array<string, string>}>
     */
    private array $merges = [
        // Same word, stored twice — once per language, as two separate rows.
        ['type' => 'library_item', 'keep' => ['de' => 'Philosophie'], 'fold' => ['en' => 'Philosophie']],
        ['type' => 'library_item', 'keep' => ['de' => 'Etatismus'], 'fold' => ['en' => 'Etatismus']],
        ['type' => 'library_item', 'keep' => ['de' => 'Kryptografie'], 'fold' => ['en' => 'Kryptografie']],
        ['type' => 'library_item', 'keep' => ['de' => 'News'], 'fold' => ['en' => 'News']],
        ['type' => 'course', 'keep' => ['de' => 'Bitcoin'], 'fold' => ['en' => 'Bitcoin']],

        // Spelling variants and typos.
        ['type' => 'library_item', 'keep' => ['de' => 'Bindle'], 'fold' => ['de' => '#Bindle ']],
        ['type' => 'library_item', 'keep' => ['de' => 'Immobilien'], 'fold' => ['de' => 'Immmobilien']],
        ['type' => 'library_item', 'keep' => ['de' => 'PlebRap'], 'fold' => ['de' => 'PlebRapCash']],

        // Same concept under two names.
        ['type' => 'library_item', 'keep' => ['de' => 'Reise'], 'fold' => ['de' => 'Travel']],
        ['type' => 'library_item', 'keep' => ['de' => 'Privacy'], 'fold' => ['de' => 'Privatsphäre']],
        ['type' => 'library_item', 'keep' => ['de' => 'Allgemein Bitcoin'], 'fold' => ['de' => 'Bitcoin']],
        ['type' => 'library_item', 'keep' => ['de' => 'Abschlussarbeit'], 'fold' => ['de' => 'wissenschaftliche Arbeit']],
        ['type' => 'library_item', 'keep' => ['de' => 'Abschlussarbeit'], 'fold' => ['de' => 'Masterarbeit']],
        ['type' => 'library_item', 'keep' => ['de' => 'Abschlussarbeit'], 'fold' => ['de' => 'Bachelorarbeit']],
    ];

    /**
     * German names the curated vocabulary spells differently.
     *
     * @var array<int, array{type: string, from: string, to: string}>
     */
    private array $renames = [
        ['type' => 'library_item', 'from' => 'community', 'to' => 'Gemeinschaft'],
        ['type' => 'library_item', 'from' => 'ratgeber', 'to' => 'Ratgeber'],
        ['type' => 'library_item', 'from' => 'kostenlos', 'to' => 'Kostenlos'],
        ['type' => 'library_item', 'from' => 'shitcoins', 'to' => 'Shitcoins'],
        ['type' => 'library_item', 'from' => 'Anlage', 'to' => 'Geldanlage'],
        ['type' => 'library_item', 'from' => 'Attacken', 'to' => 'Angriffe'],
        ['type' => 'library_item', 'from' => 'Music', 'to' => 'Musik'],
        ['type' => 'library_item', 'from' => 'Lyrics', 'to' => 'Liedtext'],
        ['type' => 'library_item', 'from' => 'Education', 'to' => 'Bildung'],
        ['type' => 'library_item', 'from' => 'Africa', 'to' => 'Afrika'],
        ['type' => 'library_item', 'from' => 'Privacy', 'to' => 'Privatsphäre'],
    ];

    public function up(): void
    {
        foreach ($this->merges as $merge) {
            $keep = $this->find($merge['type'], $merge['keep']);
            $fold = $this->find($merge['type'], $merge['fold']);

            if ($keep === null || $fold === null || $keep->id === $fold->id) {
                continue;
            }

            $this->movePivots($fold->id, $keep->id);
            $fold->delete();
        }

        foreach ($this->renames as $rename) {
            $tag = $this->find($rename['type'], ['de' => $rename['from']]);

            if ($tag === null) {
                continue;
            }

            // Skip if the target name is already taken — a merge above may have
            // produced it, and two rows with the same German name would defeat
            // TagSeeder's matching just as badly as the duplicates we are removing.
            if ($this->find($rename['type'], ['de' => $rename['to']]) !== null) {
                continue;
            }

            $tag->setTranslation('name', 'de', $rename['to']);
            $tag->save();
        }
    }

    /**
     * Re-point every taggable of the folded tag at the surviving one.
     *
     * `taggables` carries a unique index on (tag_id, taggable_id, taggable_type), so a
     * plain UPDATE would fail wherever an item already carries both tags. Those rows
     * are dropped instead — the association survives through the surviving tag.
     */
    private function movePivots(int $fromTagId, int $toTagId): void
    {
        $alreadyTagged = DB::table('taggables')
            ->where('tag_id', $toTagId)
            ->get(['taggable_id', 'taggable_type'])
            ->map(fn ($row): string => $row->taggable_type.'#'.$row->taggable_id)
            ->all();

        DB::table('taggables')
            ->where('tag_id', $fromTagId)
            ->get(['taggable_id', 'taggable_type'])
            ->each(function ($row) use ($fromTagId, $toTagId, $alreadyTagged): void {
                $key = $row->taggable_type.'#'.$row->taggable_id;

                $query = DB::table('taggables')
                    ->where('tag_id', $fromTagId)
                    ->where('taggable_id', $row->taggable_id)
                    ->where('taggable_type', $row->taggable_type);

                in_array($key, $alreadyTagged, true)
                    ? $query->delete()
                    : $query->update(['tag_id' => $toTagId]);
            });
    }

    /**
     * Find a tag by type and one locale's exact name.
     *
     * Compares in PHP over the type's tags rather than querying the JSON column, which
     * keeps this portable across SQLite and MySQL. The vocabulary is under a hundred rows.
     *
     * @param  array<string, string>  $name  locale => name
     */
    private function find(string $type, array $name): ?Tag
    {
        $locale = array_key_first($name);
        $value = $name[$locale];

        return Tag::query()
            ->where('type', $type)
            ->get()
            ->first(fn (Tag $tag): bool => $tag->getTranslation('name', $locale, false) === $value);
    }

    public function down(): void
    {
        // Intentionally empty: merged tags are gone and the original pivot assignments
        // are not recorded anywhere. Restore from a backup instead.
    }
};
