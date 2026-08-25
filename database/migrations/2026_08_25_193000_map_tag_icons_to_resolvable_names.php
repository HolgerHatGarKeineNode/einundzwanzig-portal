<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrite the Font Awesome icon names in `tags.icon` to names Flux can resolve.
 *
 * The seed vocabulary was written against Font Awesome; Flux ships Heroicons and
 * resolves `<flux:icon name="x">` by delegating to a Blade component. A name it
 * cannot resolve does not degrade to a blank space — it throws:
 *
 *   Illuminate\View\ViewException: Flux component [icon.coin] does not exist.
 *
 * Measured on the production copy on 2026-08-25: 15 of 91 tags carried one of ten
 * such names. Nothing was broken because no screen rendered `tags.icon` at all —
 * the only reference wrote the value. This migration therefore runs BEFORE the
 * moderation screen starts showing icons, not after.
 *
 * The mapping is deliberately explicit and one-directional per name, so `down()`
 * restores exactly what was there. Names outside this map are left alone: a value
 * that is neither Font Awesome nor Heroicons is somebody's mistake, and the
 * moderation screen now shows it as "<name> — not resolvable" instead of dying on
 * it. Resetting it to `tag` here would hide the mistake rather than fix it.
 *
 * Two of the ten targets deviate from the obvious literal translation:
 *   - coin → circle-stack, not banknotes. Heroicons has no coin; `banknotes` is
 *     printed fiat, which for a tag literally named "Bitcoin" is the wrong object,
 *     not merely an imprecise one. `circle-stack` keeps the round, stacked form.
 *   - thought-bubble → chat-bubble-oval-left-ellipsis. The oval bubble is the
 *     thought-bubble shape; the rectangular one is a speech bubble.
 */
return new class extends Migration
{
    /**
     * Font Awesome name => Heroicons name.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'beer-mug' => 'chat-bubble-left-right',
        'chalkboard-user' => 'presentation-chart-bar',
        'child' => 'user-group',
        'coin' => 'circle-stack',
        'graduation-cap' => 'academic-cap',
        'microphone-stand' => 'microphone',
        'seedling' => 'rocket-launch',
        'store' => 'building-storefront',
        'thought-bubble' => 'chat-bubble-oval-left-ellipsis',
        'user-secret' => 'eye-slash',
    ];

    public function up(): void
    {
        foreach (self::MAP as $from => $to) {
            DB::table('tags')->where('icon', $from)->update(['icon' => $to]);
        }
    }

    /**
     * Reversible because the map has no two sources sharing a target: every new
     * name identifies exactly one old one.
     */
    public function down(): void
    {
        foreach (array_flip(self::MAP) as $from => $to) {
            DB::table('tags')->where('icon', $from)->update(['icon' => $to]);
        }
    }
};
