<?php

namespace App\Http\Resources;

use App\Http\Controllers\Api\MeetupEventController;
use App\Jobs\DeliverWebhookJob;
use App\Models\Tag;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TWO KEYS, ONE VALUE: `locale` AND `name_locale` (issue #57).
 *
 * The hand-built tag array of `GET /api/meetup-events`
 * ({@see MeetupEventController::__invoke()}) has always called
 * this field `locale`; every response that goes through this resource — the
 * authenticated read endpoints, the write responses, the webhook and change-log
 * envelope, the course events — has always called it `name_locale`. Since PR #52 both
 * carry the same information, so only the names differed.
 *
 * The decision was ADDITIVE, not a rename: `name_locale` stays for as long as consumers
 * need it, because renaming it would break every existing parser at once — including the
 * webhook receivers, which cannot ask for a locale and read `name_locale` and
 * `translations` precisely because of that. `locale` is the name new consumers should
 * read; it is the one the public list endpoint already uses.
 *
 * Both keys are filled from the same `$displayLocale` and can never disagree. Dropping
 * `name_locale` is a separate, announced breaking change, not a cleanup.
 *
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * A tag as seen through one language.
     *
     * Tags are multilingual: one tag carries a name in each of the nine portal languages.
     * Which one you get depends on what the request asked for — `?locale=`, then
     * `Accept-Language`, see {@see self::requestedLocale()} — and `locale` (as well as
     * its older twin `name_locale`) always tells you which one you actually got, which
     * is not always the one you asked for.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $requestedLocale = self::requestedLocale($request);
        // Resolved once: displayLocale() walks the whole candidate chain and hits
        // getTranslation() per candidate, and `name`, `locale`/`name_locale` and `slug`
        // must agree on the answer anyway.
        $displayLocale = $this->displayLocale($requestedLocale);

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
             * language is used, then English, then whatever exists. Check `locale` to
             * find out which one you are looking at.
             */
            // Resolved through the display chain for the language the client asked
            // for, so a consumer asking in Czech gets the German name rather than an
            // empty string when only German exists.
            'name' => $this->displayName($requestedLocale),
            /**
             * The language `name` and `slug` are actually in — an ISO 639-1 code such as
             * `de` or `cs`. Differs from the requested language whenever a fallback kicked
             * in, which is your cue to show the name as a foreign-language label.
             *
             * Read this one. It is the same name `GET /api/meetup-events` uses, and the
             * same value as `name_locale`, which is kept only for existing parsers.
             */
            'locale' => $displayLocale,
            /**
             * The same value as `locale`, under the older name.
             *
             * Kept because every existing parser of this resource reads it — see the
             * class docblock. New consumers should read `locale`.
             */
            'name_locale' => $displayLocale,
            /** URL-safe form of `name`, in the language given by `locale`. */
            'slug' => $this->getTranslation('slug', $displayLocale ?? app()->getLocale(), false),
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

    /**
     * The language a tag name was asked for, or null to leave the choice to the
     * display chain's own default (`app()->getLocale()`).
     *
     * `?locale=` takes precedence over `Accept-Language` because it is explicit and
     * survives a client that cannot set custom headers. Neither is required: absent
     * both, this returns null, and {@see Tag::displayName()} behaves exactly as it
     * did before this method existed.
     *
     * WHY THIS LIVES ON THE RESOURCE AND NOT ON THE CONTROLLER: `TagResource` is
     * reached from three directions — the two read endpoints, the write endpoints'
     * responses, and {@see ChangeRecorder::data()}, which
     * resolves the resource with `request()` while building the change-log envelope.
     * Only the first has a controller to ask. `MeetupEventController` hands its own
     * hand-built tag array to this same method rather than keeping a second copy of
     * the precedence rule.
     *
     * The envelope case is worth spelling out: it is built synchronously inside the
     * WRITE request, so the language of that request is what gets frozen into
     * `api_changes.payload` — and from there into every webhook delivery of it,
     * because {@see DeliverWebhookJob} re-sends the stored bytes and never
     * re-renders the resource. A queue worker has no request and cannot resolve a
     * locale; `locale` (with its older twin `name_locale`) and `translations` travel
     * with each tag precisely so a receiver never has to guess which language it got.
     */
    public static function requestedLocale(Request $request): ?string
    {
        $queryLocale = $request->query('locale');

        if (is_string($queryLocale) && $queryLocale !== '') {
            return $queryLocale;
        }

        $preferred = $request->getLanguages()[0] ?? null;

        return $preferred === null ? null : str($preferred)->before('_')->toString();
    }
}
