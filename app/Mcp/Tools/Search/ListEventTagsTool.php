<?php

namespace App\Mcp\Tools\Search;

use App\Http\Resources\TagResource;
use App\Mcp\Tools\Concerns\ResolvesEventTags;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The whole event-tag vocabulary in one call (issue #117).
 *
 * A FLAT LIST, NOT A SEARCH. The `meetup_event` group holds sixteen tags; a search
 * parameter would cost a round trip and could still answer with a name the caller then
 * has to guess a spelling for. {@see ListCountriesTool} is the existing precedent for
 * a lookup tool that simply hands over its list.
 *
 * Every entry carries `translations`, the tag's name in each language it has, because
 * that is what create-meetup-event and update-meetup-event match against — any of the
 * nine, case-insensitively. An agent that read this list can therefore always pick a
 * name that resolves, which is the point: unknown names are refused, never created.
 *
 * The list is the picker's list. It runs through the same scope the resolver uses
 * ({@see ResolvesEventTags::selectableEventTags()}), so a tag offered here is by
 * construction a tag that can be attached, and one that cannot be attached is not
 * offered.
 */
#[IsReadOnly]
#[Description('Listet alle Tags, die einem Meetup-Termin zugeordnet werden können (Gruppe "meetup_event", 16 Einträge, keine Suche nötig). Jeder Eintrag enthält id, name, locale, featured, approved und unter "translations" den Namen in jeder vorhandenen der neun Sprachen. Genau diese Namen akzeptieren create-meetup-event und update-meetup-event im Feld "tags"; ein anderer Name wird abgelehnt und NIE neu angelegt.')]
class ListEventTagsTool extends Tool
{
    use ResolvesEventTags;

    public function handle(Request $request): Response
    {
        $tags = $this->selectableEventTags($request->user())
            // `ordered()` is the moderation screen's sequence (tags.order_column) and
            // sortByDesc('featured') lifts the curated block to the top — the same two
            // steps the picker takes, so an agent sees the vocabulary in the order a
            // human organiser does. PHP's sort is stable, so the sequence survives
            // inside each block.
            ->ordered()
            ->get()
            ->sortByDesc('featured')
            ->values();

        return Response::json(TagResource::collection($tags)->resolve());
    }

    /**
     * No parameters: sixteen entries are the whole answer.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
