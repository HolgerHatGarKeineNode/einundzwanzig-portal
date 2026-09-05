<?php

namespace App\Support;

/**
 * The languages a tag can carry, and which one the current request writes into.
 *
 * The list itself lives in config/einundzwanzig.tag_locales. What this class adds is
 * the resolution rule: the request's locale is only usable as a tag locale if the tag
 * vocabulary actually has that language. Without the check a visitor browsing in a
 * language outside the nine would write a name into a locale the picker never reads —
 * invisible everywhere, and impossible to find again.
 *
 * Extracted from tags.moderation, which already resolved it this way for the
 * description field; the picker needs the same answer for `tags.source_locale`, and
 * two copies of a rule like this drift.
 */
class TagLocales
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            (array) config('einundzwanzig.tag_locales', []),
            static fn ($locale): bool => is_string($locale) && $locale !== ''
        ));
    }

    /**
     * The tag locale this request reads and writes: the current language when the
     * vocabulary has it, the app's fallback when it has that, else the first configured.
     */
    public static function current(): string
    {
        $locales = self::all();
        $current = app()->getLocale();

        if (in_array($current, $locales, true)) {
            return $current;
        }

        $fallback = (string) config('app.fallback_locale');

        return in_array($fallback, $locales, true) ? $fallback : (string) ($locales[0] ?? 'de');
    }
}
