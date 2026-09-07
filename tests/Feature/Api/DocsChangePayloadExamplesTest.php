<?php

use App\Http\Resources\MeetupEventResource;
use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use App\Models\MeetupEvent;

/*
|--------------------------------------------------------------------------
| The documented change payloads against the resources that produce them
|--------------------------------------------------------------------------
|
| /docs/websockets and /docs/webhooks both print full change envelopes, and
| both promise in prose that `data` is what the change recorder really ships.
| Between them and the resources there is no mechanism at all — the JSON is a
| heredoc in a Blade file — so the promise holds only as long as somebody
| remembers to edit two pages when a field is added. Nobody did, twice over:
|
|   - `meetup-event`: `links` (#70) and the five `*_iso` twins (#85, #125)
|     were missing from both pages, six fields in each.
|   - `meetup`: `nostr_publishing_enabled` and the three `*_iso` twins were
|     missing from the websockets page, found while gating the first repair.
|
| This is the mechanism. It fails when a field is added to a resource and not
| to the page, and it fails the other way round too — a field invented in the
| documentation is just as wrong as one left out.
|
| WHY resolve() AND NOT toArray(). A JsonResource may hand back MissingValue
| placeholders that never reach the wire; MeetupEventResource does exactly
| that for `tags`, and MeetupResource for `is_leader`, which is
| `whenPivotLoaded`. `resolve()` is the step that drops them, and it is the
| step ChangeRecorder::data() takes as well, so this compares against the
| payload as it ships and not against the method body. Each resource is
| loaded with exactly the relations ChangeRecorder::RESOURCES lists for it —
| `tags` for the event, `media` for the meetup — because that is what decides
| which conditional fields survive.
|
| WHY THE SETS ARE SORTED. Field ORDER is a readability question, not a
| contract — a consumer parses JSON. Sorting keeps the guard on the thing
| that matters and off a diff that would go red for a cosmetic move.
|
| THE ELLIPSIS IS A DOCUMENTED SHORTHAND, NOT A GAP. The webhooks page shows
| the meetup payload abbreviated, ending in `"…": "the full MeetupResource
| shape"`, because the page is about delivery and the field list is the other
| page's job. An abbreviated example cannot be compared for equality, so it is
| held to the two things that can still be wrong: every field it does name
| must exist, and it must genuinely be shorter than the real set — an ellipsis
| after a complete list would be a lie of a different kind.
|
| NOT `expect(...)->not->toContain(...)`. That matcher is variadic here, and a
| negated call swallows its own failure message: the assertion stays green and
| reports nothing. Every assertion below is an explicit array comparison, the
| same form the pint.json cross-check in SingleFileComponentsCompileTest uses.
*/

/** The key a payload example uses to say "and the rest of the fields". */
const DOCS_PAYLOAD_ELLIPSIS = '…';

/**
 * Every `<<<'JSON' … JSON,` heredoc of a docs component, decoded.
 *
 * Reading the Blade source rather than the rendered page on purpose: the page
 * prints the JSON HTML-escaped inside <pre>, and unescaping it back would put
 * a second thing that can be wrong between the file and the assertion.
 *
 * The trailing comma in the pattern is load-bearing. It matches the heredocs
 * that sit in the `payloadExamples()` array and skips the one closed with
 * `JSON;`, which is the whole `/api/changes` response — that one abbreviates
 * every `data` down to three fields on purpose and is not a field list.
 *
 * @return list<array<string, mixed>>
 */
function docsJsonExamples(string $relativePath): array
{
    $source = file_get_contents(base_path($relativePath));

    expect($source)->toBeString(sprintf('The docs component %s is gone; this guard can no longer find it.', $relativePath));

    preg_match_all("/<<<'JSON'\n(.*?)\n\s*JSON,/s", $source, $matches);

    return collect($matches[1])
        ->map(function (string $json) use ($relativePath): array {
            $decoded = json_decode($json, true);

            expect($decoded)->toBeArray(sprintf('A JSON example in %s does not parse: %s', $relativePath, json_last_error_msg()));

            return $decoded;
        })
        ->all();
}

/**
 * The one documented envelope for this resource that carries a field list.
 *
 * `deleted` carries `data: null` by contract — there is no field set to
 * compare — so it drops out here rather than being special-cased below.
 *
 * @return array<string, mixed>
 */
function documentedPayload(string $relativePath, string $resourceName): array
{
    $envelopes = collect(docsJsonExamples($relativePath))
        ->filter(fn (array $envelope): bool => ($envelope['resource'] ?? null) === $resourceName)
        ->filter(fn (array $envelope): bool => is_array($envelope['data'] ?? null))
        ->values();

    /*
     * The control on the guard itself. Without it, renaming the example or
     * dropping it would leave an empty comparison that passes, and the page
     * could then say anything at all.
     */
    expect($envelopes)->toHaveCount(
        1,
        sprintf(
            '%s no longer holds exactly one %s example with a data object; this guard has lost its subject.',
            $relativePath,
            $resourceName,
        ),
    );

    return $envelopes->first()['data'];
}

dataset('documented change payloads', [
    'websockets, meetup-event' => ['resources/views/livewire/docs/websockets.blade.php', 'meetup-event'],
    'webhooks, meetup-event' => ['resources/views/livewire/docs/webhooks.blade.php', 'meetup-event'],
    'websockets, meetup' => ['resources/views/livewire/docs/websockets.blade.php', 'meetup'],
    'webhooks, meetup' => ['resources/views/livewire/docs/webhooks.blade.php', 'meetup'],
]);

it('documents the field set the resource really produces', function (string $relativePath, string $resourceName): void {
    $real = collect(array_keys(match ($resourceName) {
        'meetup-event' => json_decode(json_encode(
            MeetupEventResource::make(MeetupEvent::factory()->create()->load('tags'))->resolve(request()),
        ) ?: '{}', true),
        'meetup' => json_decode(json_encode(
            MeetupResource::make(Meetup::factory()->create()->load('media'))->resolve(request()),
        ) ?: '{}', true),
    }))->sort()->values()->all();

    $documented = documentedPayload($relativePath, $resourceName);
    $abbreviated = array_key_exists(DOCS_PAYLOAD_ELLIPSIS, $documented);

    $documentedKeys = collect(array_keys($documented))
        ->reject(fn (string $key): bool => $key === DOCS_PAYLOAD_ELLIPSIS)
        ->sort()->values()->all();

    if (! $abbreviated) {
        expect($documentedKeys)->toBe(
            $real,
            sprintf(
                "%s and the %s resource disagree about the payload.\nMissing from the page: %s\nOn the page but not in the resource: %s",
                $relativePath,
                $resourceName,
                implode(', ', array_diff($real, $documentedKeys)) ?: '—',
                implode(', ', array_diff($documentedKeys, $real)) ?: '—',
            ),
        );

        return;
    }

    // An abbreviated example: every field it names must be real …
    expect(array_values(array_diff($documentedKeys, $real)))->toBe(
        [],
        sprintf(
            'The abbreviated %s example in %s names fields the resource does not produce: %s',
            $resourceName,
            $relativePath,
            implode(', ', array_diff($documentedKeys, $real)),
        ),
    );

    // … and the ellipsis must stand for something.
    expect(count($documentedKeys))->toBeLessThan(
        count($real),
        sprintf(
            'The %s example in %s ends in an ellipsis but already lists every field; drop the ellipsis or the shorthand is misleading.',
            $resourceName,
            $relativePath,
        ),
    );
})->with('documented change payloads');
