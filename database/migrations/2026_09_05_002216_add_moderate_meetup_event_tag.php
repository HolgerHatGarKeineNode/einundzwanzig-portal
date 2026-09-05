<?php

use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the missing middle of the event difficulty scale — "Mittelstufe" / "Moderate" —
 * to a database that already carries the seeded `meetup_event` vocabulary (issue #68).
 *
 * TagSeeder alone is not enough for an existing database. It creates the row, but
 * Spatie's SortableTrait numbers a new tag "highest order_column + 1", so the seeder
 * would append it after the last library tag and the picker would offer it at the very
 * bottom of the event list instead of between "Einsteiger" and "Fortgeschrittene". The
 * position is the point of the issue, so it is set here explicitly.
 *
 * Nothing happens when the anchor "Fortgeschrittene" is absent: that is a fresh
 * database (and every test database), where TagSeeder seeds the whole vocabulary in
 * file order and the new tag lands in the right place by construction. Re-running is
 * likewise a no-op once the tag exists, so the migration is idempotent.
 *
 * No `taggables` row is read or written: this adds a choice, it does not retag anything.
 *
 * The names are frozen here rather than read from database/seeders/data/tags.php. A
 * migration is a record of what ran, and a later edit to the vocabulary file must not
 * change what this one did. AddModerateMeetupEventTagTest asserts the two agree today.
 */
return new class extends Migration
{
    private const TYPE = 'meetup_event';

    private const GERMAN = 'Mittelstufe';

    /** The tag the new one must sort in front of. */
    private const ANCHOR = 'Fortgeschrittene';

    private const ICON = 'book-open';

    /** @var array<string, string> */
    private const NAMES = [
        'de' => 'Mittelstufe',
        'en' => 'Moderate',
        'cs' => 'Mírně pokročilí',
        'es' => 'Intermedio',
        'hu' => 'Középhaladók',
        'lv' => 'Vidēji pieredzējušiem',
        'nl' => 'Halfgevorderden',
        'pl' => 'Średniozaawansowani',
        'pt' => 'Intermédio',
    ];

    public function up(): void
    {
        $anchor = $this->findByGermanName(self::ANCHOR);

        if ($anchor === null || $this->findByGermanName(self::GERMAN) !== null) {
            return;
        }

        $position = (int) $anchor->order_column;

        /*
         * Make room at that position. `order_column` is one ladder across all tag
         * types — SortableTrait counts from the highest row of the whole table — so the
         * shift is global too. Every relative order stays exactly as it was; only the
         * numbers move up by one.
         */
        DB::table('tags')->where('order_column', '>=', $position)->increment('order_column');

        $tag = new Tag(['type' => self::TYPE]);

        foreach (self::NAMES as $locale => $name) {
            $tag->setTranslation('name', $locale, $name);
        }

        $tag->icon = self::ICON;
        $tag->featured = false;
        $tag->approved_at = now();
        $tag->save();

        // Set after the save, not before: SortableTrait's `creating` hook overwrites
        // whatever order_column the model carries with "highest + 1".
        $tag->newQuery()->whereKey($tag->id)->update(['order_column' => $position]);
    }

    /**
     * Removes the tag again and closes the gap it left in the ladder.
     *
     * Except when an event has been tagged with it in the meantime — deleting the row
     * then cascades into `taggables` and silently strips a tag off somebody's event.
     * A vocabulary entry in use is left standing; rolling back the migration must not
     * change data the migration never touched.
     */
    public function down(): void
    {
        $tag = $this->findByGermanName(self::GERMAN);

        if ($tag === null || DB::table('taggables')->where('tag_id', $tag->id)->exists()) {
            return;
        }

        $position = (int) $tag->order_column;

        $tag->delete();

        DB::table('tags')->where('order_column', '>', $position)->decrement('order_column');
    }

    /**
     * Matched on type plus German name, the source language of the vocabulary, and
     * compared in PHP rather than through the JSON column — the same approach TagSeeder
     * takes, and for the same reason: it works on SQLite and MySQL alike.
     */
    private function findByGermanName(string $german): ?Tag
    {
        return Tag::query()
            ->where('type', self::TYPE)
            ->get()
            ->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de', false) === $german);
    }
};
