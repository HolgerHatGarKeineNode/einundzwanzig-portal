<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * A tag as seen through one language.
     *
     * Tags are multilingual: one tag carries a name in each of the nine portal languages.
     * Which one you get depends on the request locale (`Accept-Language` or the URL
     * segment), and `name_locale` always tells you which one you actually got — that is
     * not always the one you asked for.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /**
             * The group this tag belongs to: `meetup_event`, `course` or `library_item`.
             * A tag never crosses groups, so the same word can exist once per group.
             */
            'type' => $this->type,
            /**
             * The name in the requested language, with a fallback.
             *
             * Never empty: when a tag has no name in the requested language, the portal
             * language is used, then English, then whatever exists. Check `name_locale`
             * to find out which one you are looking at.
             */
            // Resolved through the display chain, so a consumer asking in Czech gets
            // the German name rather than an empty string when only German exists.
            'name' => $this->displayName(),
            /**
             * The language `name` and `slug` are actually in — an ISO 639-1 code such as
             * `de` or `cs`. Differs from the requested language whenever a fallback kicked
             * in, which is your cue to show the name as a foreign-language label.
             */
            'name_locale' => $this->displayLocale(),
            /** URL-safe form of `name`, in the language given by `name_locale`. */
            'slug' => $this->getTranslation('slug', $this->displayLocale() ?? app()->getLocale(), false),
            /**
             * Whether the tag is one of the curated suggestions offered before the user
             * types anything. Use it to build a starting list rather than dumping the
             * whole vocabulary.
             */
            'featured' => $this->featured,
            /**
             * False for a tag a user proposed that no editor has cleared yet. Unapproved
             * tags are visible only on their proposer's own event.
             */
            'approved' => $this->isApproved(),
            /**
             * Every translation of the name, keyed by language code — for clients that
             * render their own language switcher instead of relying on `name`.
             *
             * @example {"de": "Vortrag", "cs": "Přednáška", "en": "Talk"}
             */
            'translations' => $this->getTranslations('name'),
        ];
    }
}
