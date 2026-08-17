<?php

/**
 * What is left to test here is the payload, not the migration run.
 *
 * The migration itself copied places off `venues`, and that table no longer exists — its
 * behaviour cannot be reproduced on a fully migrated database any more. The 16 rows it
 * ships, however, still travel with the repository and still land in production the next
 * time somebody migrates from scratch. Those rows are worth guarding: four bad matches were
 * caught by reading them, not by running anything.
 */
it('ships only hand-verified matches, none below the high bar', function () {
    $matches = json_decode((string) file_get_contents(database_path('data/venue-osm-matches.json')), true);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as $match) {
        expect($match['similarity'])->toBeGreaterThanOrEqual(0.85, "zu schwach: {$match['venue_name']}")
            ->and($match['osm_type'])->toBeIn(['node', 'way', 'relation'])
            ->and($match['osm_id'])->toBeInt()
            ->and($match['osm_name'])->not->toBeEmpty()
            ->and($match['venue_name'])->not->toBeEmpty();
    }
});

it('carries no duplicate venue or place', function () {
    $matches = json_decode((string) file_get_contents(database_path('data/venue-osm-matches.json')), true);

    $venueIds = array_column($matches, 'venue_id');
    $places = array_map(fn (array $m): string => $m['osm_type'].'/'.$m['osm_id'], $matches);

    expect($venueIds)->toHaveCount(count(array_unique($venueIds)))
        // Two venues pointing at one place would mean the matcher collapsed two real
        // locations into one — exactly the failure the verification pass looked for.
        ->and($places)->toHaveCount(count(array_unique($places)));
});
