<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use App\Support\TagLocales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Resolves meetup-event tags by NAME for the MCP write tools (issue #117).
 *
 * Three rules hold this together, and all three are load-bearing.
 *
 * 1. NOTHING IS EVER CREATED. Every spatie write path — attachTags(), syncTags(),
 *    syncTagsWithType(), even the `tags` mass-assignment mutator on HasTags — routes
 *    unknown strings through Tag::findOrCreate() and invents a tag for anything it
 *    cannot find. An LLM caller guessing at names would be a duplicate generator no
 *    approval queue catches, and creating a tag is the one irreversible move in this
 *    feature. So names are resolved HERE and handed on as Tag MODELS; findOrCreate()
 *    returns a model it was given untouched, which is what makes the sync in the
 *    calling tool incapable of creating.
 * 2. ONLY WHAT THE PICKER OFFERS. The lookup runs through Tag::scopeSelectableBy(),
 *    the same scope resources/views/livewire/tags/picker.blade.php uses, for the
 *    reason that file states: a crafted request must not be able to attach someone
 *    else's unapproved suggestion. An MCP call is exactly such a request path. A tag
 *    outside the scope therefore reads as "not found" rather than "not allowed" —
 *    the caller could not have picked it either way, and the wording does not
 *    disclose another user's pending suggestion.
 * 3. ALL OR NOTHING. A list is resolved completely before the caller writes anything.
 *    Half an applied tag list is worse than a rejected one, because the caller has no
 *    way to tell which half arrived.
 *
 * Matching is case-insensitive across all nine tag locales and scoped to the
 * `meetup_event` type — the shape of findByAnyLocale() in the picker. It deliberately
 * does NOT go through Tag::displayName(): since a tag may legitimately carry only one
 * locale, displayName() would answer with a fallback language, and a name the tag does
 * not actually have would start matching.
 */
trait ResolvesEventTags
{
    /**
     * The tag group an event's tags belong to. A tag never crosses groups, so this is
     * also what keeps the 35 cross-type name collisions in the curated vocabulary
     * (`Einsteiger` in meetup_event vs. library_item, `Bitcoin` in meetup_event vs.
     * course) out of the way: within meetup_event there are none.
     */
    protected const EVENT_TAG_TYPE = 'meetup_event';

    /**
     * The `tags` argument in the three states #117 defines.
     *
     * - key absent  → null, "leave the tags alone"
     * - `null`      → null as well, see below
     * - `[]`        → an empty collection, "remove every tag"
     * - `["a","b"]` → the resolved tags, to be applied as the complete new set
     *
     * An explicit `null` reads as "not given", never as "no tags". That is the rule
     * this repo already carries on `links` ({@see MeetupEvent::booted()}),
     * and issue #70 is the reason it is spelled out rather than assumed: Laravel's
     * `sometimes` counts an explicitly sent null as PRESENT, so a null sailed through
     * validation and emptied a stored list of five labelled entries. MCP has no
     * FormRequest in the path at all and its JSON schema makes `null` reachable for
     * any property, so the same input has to be answered here — and answered in the
     * non-destructive direction.
     *
     * @return Collection<int, Tag>|Response|null
     */
    protected function resolveTagArgument(Request $request): Collection|Response|null
    {
        $arguments = $request->toArray();

        if (! array_key_exists('tags', $arguments) || $arguments['tags'] === null) {
            return null;
        }

        $names = $arguments['tags'];

        if (! is_array($names)) {
            return Response::error('"tags" muss eine Liste von Tag-Namen sein, z. B. ["Vortrag", "Einsteiger"]. Die verfügbaren Namen liefert list-event-tags.');
        }

        return $this->resolveEventTags($names, $request->user());
    }

    /**
     * Turns a list of names into the tags they name, or into the error that says why
     * it could not be done. Never returns a partial list.
     *
     * @param  array<array-key, mixed>  $names
     * @return Collection<int, Tag>|Response
     */
    protected function resolveEventTags(array $names, ?Authenticatable $user): Collection|Response
    {
        /** @var array<int, array{given: string, needle: string}> $requested */
        $requested = [];

        foreach ($names as $name) {
            if (! is_string($name)) {
                return Response::error('"tags" muss eine Liste von Tag-Namen (Text) sein, z. B. ["Vortrag", "Einsteiger"]. Die verfügbaren Namen liefert list-event-tags.');
            }

            $given = $this->normalisedTagName($name);

            if ($given === '') {
                return Response::error('Ein leerer Eintrag in "tags" ist kein Tag. Bitte den Eintrag weglassen — oder [] senden, um alle Tags zu entfernen.');
            }

            $requested[] = ['given' => $given, 'needle' => mb_strtolower($given)];
        }

        $candidates = $this->selectableEventTags($user)->get();

        /** @var Collection<int, Tag> $resolved */
        $resolved = collect();

        foreach ($requested as ['given' => $given, 'needle' => $needle]) {
            $matches = $candidates
                ->filter(fn (Tag $tag): bool => $this->tagCarriesName($tag, $needle))
                ->values();

            if ($matches->isEmpty()) {
                return Response::error("Tag \"{$given}\" wurde nicht gefunden. Es wurde NICHTS geändert und es wird kein Tag neu angelegt — bitte list-event-tags aufrufen und einen der dort genannten Namen verwenden (jede der neun Sprachen wird erkannt).");
            }

            if ($matches->count() > 1) {
                /*
                 * Never a silent first hit. Today the vocabulary has no collision
                 * inside meetup_event (0 across 112 distinct name strings for 16 tags),
                 * so this branch is unreachable through the seeded data — but a tag is
                 * user-editable, and the day two of them share a word in any of the
                 * nine languages, picking whichever the database returned first would
                 * attach a tag nobody asked for.
                 */
                $kandidaten = $matches
                    ->map(fn (Tag $tag): string => '#'.$tag->getKey().' '.$tag->displayName())
                    ->join('; ');

                return Response::error("Mehrere Tags passen zu \"{$given}\": {$kandidaten}. Es wurde NICHTS geändert — bitte den Namen in einer Sprache angeben, in der er eindeutig ist (siehe list-event-tags).");
            }

            $resolved->push($matches->first());
        }

        return $resolved->unique(fn (Tag $tag) => $tag->getKey())->values();
    }

    /**
     * Every tag this user may attach to an event: the `meetup_event` group, filtered
     * by the picker's own scope. Shared with list-event-tags so the tool that offers
     * the vocabulary and the resolver that accepts it can never drift apart.
     */
    protected function selectableEventTags(?Authenticatable $user): Builder
    {
        return Tag::query()
            ->where('type', self::EVENT_TAG_TYPE)
            // Anything that is not a User is treated as nobody, which yields the
            // approved tags only — the restrictive answer, not the permissive one.
            ->selectableBy($user instanceof User ? $user : null);
    }

    /**
     * Whether the tag carries this (already lower-cased and whitespace-normalised)
     * name in any of the nine tag locales.
     */
    private function tagCarriesName(Tag $tag, string $needle): bool
    {
        foreach (TagLocales::all() as $locale) {
            $name = (string) $tag->getTranslation('name', $locale, false);

            if ($name !== '' && mb_strtolower($this->normalisedTagName($name)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trimmed, with runs of whitespace collapsed — the same normalisation the picker
     * applies before it stores a name, so what an organiser typed there and what an
     * agent sends here compare equal.
     */
    private function normalisedTagName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }
}
