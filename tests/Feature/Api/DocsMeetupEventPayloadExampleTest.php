<?php

use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;

/*
|--------------------------------------------------------------------------
| The documented meetup-event payload against the real resource
|--------------------------------------------------------------------------
|
| /docs/websockets and /docs/webhooks both print a full `meetup-event`
| envelope, and both promise in prose that its `data` is what the change
| recorder really ships. Between them and MeetupEventResource there is no
| mechanism at all — the JSON is a heredoc in a Blade file — so the promise
| holds only as long as somebody remembers to edit two pages when a field is
| added to the resource. Nobody did: `links` (#70) and the five `*_iso`
| twins (#85, #125) were all missing when this guard was written, six fields
| in each of the two examples.
|
| This is the mechanism. It fails when a field is added to the resource and
| not to the page, and it fails the other way round too — a field invented in
| the documentation is just as wrong as one left out.
|
| WHY resolve() AND NOT toArray(). A JsonResource may hand back MissingValue
| placeholders that never reach the wire; MeetupEventResource does exactly
| that for `tags`, which is `whenLoaded`. `resolve()` is the step that drops
| them, and it is the step ChangeRecorder::data() takes as well, so this
| compares against the payload as it ships and not against the method body.
| `tags` is loaded here for the same reason the recorder loads it
| (ChangeRecorder::RESOURCES lists it under `relations`).
|
| WHY THE SETS ARE SORTED. Field ORDER is a readability question, not a
| contract — a consumer parses JSON. Sorting keeps the guard on the thing
| that matters and off a diff that would go red for a cosmetic move.
|
| NOT `expect(...)->not->toContain(...)`. That matcher is variadic here, and
| a negated call swallows its own failure message: the assertion stays green
| and reports nothing. Both assertions below are explicit array comparisons,
| the same form the pint.json cross-check in SingleFileComponentsCompileTest
| uses.
*/

/**
 * Every `<<<'JSON' … JSON,` heredoc of a docs component, decoded.
 *
 * Reading the Blade source rather than the rendered page on purpose: the page
 * prints the JSON HTML-escaped inside <pre>, and unescaping it back would put
 * a second thing that can be wrong between the file and the assertion.
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

dataset('docs pages with a meetup-event payload', [
    'websockets' => 'resources/views/livewire/docs/websockets.blade.php',
    'webhooks' => 'resources/views/livewire/docs/webhooks.blade.php',
]);

it('documents the field set MeetupEventResource really produces', function (string $relativePath): void {
    $event = MeetupEvent::factory()->create();

    $real = collect(array_keys(json_decode(
        json_encode(MeetupEventResource::make($event->load('tags'))->resolve(request())) ?: '{}',
        true,
    )))->sort()->values()->all();

    $documented = collect(docsJsonExamples($relativePath))
        ->filter(fn (array $envelope): bool => ($envelope['resource'] ?? null) === 'meetup-event')
        // `deleted` carries `data: null` by contract — there is no field set to compare.
        ->filter(fn (array $envelope): bool => is_array($envelope['data'] ?? null))
        ->values();

    /*
     * The control on the guard itself. Without it, renaming the example or
     * dropping it would leave an empty comparison that passes, and the page
     * could then say anything at all.
     */
    expect($documented)->toHaveCount(
        1,
        sprintf('%s no longer holds exactly one meetup-event example with a data object; this guard has lost its subject.', $relativePath),
    );

    $documentedKeys = collect(array_keys($documented->first()['data']))->sort()->values()->all();

    expect($documentedKeys)->toBe(
        $real,
        sprintf(
            "%s and MeetupEventResource disagree about the meetup-event payload.\nMissing from the page: %s\nOn the page but not in the resource: %s",
            $relativePath,
            implode(', ', array_diff($real, $documentedKeys)) ?: '—',
            implode(', ', array_diff($documentedKeys, $real)) ?: '—',
        ),
    );
})->with('docs pages with a meetup-event payload');
