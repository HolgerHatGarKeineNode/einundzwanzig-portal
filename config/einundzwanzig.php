<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tag locales
    |--------------------------------------------------------------------------
    |
    | The languages a tag can carry a name and description in. This list is the
    | single source of truth for the tag picker, which renders one hidden alias
    | per locale so a Czech organiser can find a tag that only exists in German.
    |
    | Deliberately NOT read from config/translatable.php: that file belongs to
    | the unused astrotomic package, lists only en/fr/es, and is read by nothing.
    | Iterating over it would silently narrow the picker to three languages —
    | no error, no failing test, just a search that quietly stops working.
    |
    | Must stay in sync with the directories under lang/. TagLocalesTest guards it.
    |
    */

    'tag_locales' => ['cs', 'de', 'en', 'es', 'hu', 'lv', 'nl', 'pl', 'pt'],

    /*
    |--------------------------------------------------------------------------
    | Tag editors
    |--------------------------------------------------------------------------
    |
    | Nostr npubs allowed to create tags directly. Everyone else may still
    | suggest one — it is usable on their own event immediately but stays out of
    | other people's suggestions until a tag editor approves it.
    |
    | Seeded with the board of Einundzwanzig e.V., copied on 2026-08-17 from
    | einundzwanzig-verein, config/einundzwanzig/config.php ('current_board').
    | That repository remains the authoritative list — this is a copy, so it
    | needs updating by hand whenever the board changes. Extra editors who are
    | not board members can simply be appended here.
    |
    */

    'tag_editors' => [
        'npub1pt0kw36ue3w2g4haxq3wgm6a2fhtptmzsjlc2j2vphtcgle72qesgpjyc6',
        'npub1gvqkjccl9urg93svaw60jqkk3ux8r3ycl5t3rlvc9uzjeu0agfuss8x8qy',
        'npub10t8npnmqhpwx9w8k232kess7gqtdlr6kqjemdzf8jnughwqd0gwsez0924',
        'npub1r8343wqpra05l3jnc4jud4xz7vlnyeslf7gfsty7ahpf92rhfmpsmqwym8',
        'npub17fqtu2mgf7zueq2kdusgzwr2lqwhgfl2scjsez77ddag2qx8vxaq3vnr8y',
        'npub1v4lgwjv7qfn3t7qjscpsgz9vqvspf6hecdp2ckgp0dz89uqn5slsgrhw3p',
        'npub14r770s5wrqpm8jmzur5arnm9aum9x0wasaxwczael54xhjggl7ws5lygc6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries requiring tags on events
    |--------------------------------------------------------------------------
    |
    | Lower-case values of countries.code. Events belonging to a country in this
    | list cannot be saved without at least one tag; everywhere else tags stay
    | optional so existing records and other communities are unaffected.
    |
    | Requested for Czechia in issue #4, built as a list so other communities can
    | opt in without a code change.
    |
    */

    'tags_required_countries' => ['cz'],

];
