<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    'info' => [
        /*
         * API version.
         *
         * 2.0.0 because the venue endpoints were removed outright and the course-event
         * payload changed shape — under SemVer that is a major bump, not a minor one.
         * Consumers reading this number are the reason it has to be honest.
         */
        'version' => env('API_VERSION', '2.0.0'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => <<<'MARKDOWN'
        Welcome to the **EINUNDZWANZIG API** – the public interface of the
        [EINUNDZWANZIG](https://portal.einundzwanzig.space) Bitcoin community platform.

        This API gives you access to the data of the decentralized German-speaking Bitcoin
        movement: meetups and their events, courses and course events, lecturers, cities and the
        geo data behind the community map.

        ## Prefer an AI assistant?

        You do not need to write code to create data: connect the portal to **claude.ai** as a
        connector and manage meetups, events and courses straight from a chat. The illustrated
        step-by-step guide (in German) is at [/ki-assistent](/ki-assistent).

        ## Realtime updates

        You do not have to poll a full export and diff it against your cache. `GET /api/changes`
        is a cursor-paginated change log of every create, update and delete — deletions included,
        which are invisible anywhere else because nothing here is soft-deleted. Two public
        WebSocket channels (`portal` and `meetup-events`) carry the same envelope within
        milliseconds, without authentication.

        The channels are not HTTP routes and therefore cannot appear in this reference. Their
        names, the payload contract, a working TypeScript client and the gaps you have to plan
        around are documented at [/docs/websockets](/docs/websockets).

        ## Authentication

        Most **read endpoints** are public and require no token.

        **Write endpoints** (creating/updating courses & course events) require a personal access
        token. Create one under *Settings → API Tokens* and send it as a bearer token:

        ```http
        Authorization: Bearer <your-token>
        ```

        ## Where an event takes place

        Every event describes its location in up to three layers, and only the first is required:

        | Field | Meaning |
        |---|---|
        | `city_id` | The town. Required for course events — listings are filtered by country through it. |
        | `location` | The address in plain words, as an organiser would write it on a flyer. Always the readable answer, including "room to be confirmed". |
        | `osm_*` | The exact spot on the map, optional. Six fields that together identify an OpenStreetMap object. |

        `location` and the map place are **not** alternatives: an event with a map place keeps its
        free text, because "Bürgerhaus, side entrance" says something no coordinate does.

        To fill the `osm_*` fields, look the place up via
        [Nominatim](https://nominatim.openstreetmap.org/) and copy `osm_type`, `osm_id`, `name`,
        `display_name`, `lat` and `lon` across. Mind their
        [usage policy](https://operations.osmfoundation.org/policies/nominatim/): at most one
        request per second, and a real User-Agent is required.

        `osm_type` and `osm_id` must always travel together — ids are unique per type, not
        globally. Sending one without the other is rejected.

        ## Multilingual tags

        Events carry topic tags, and a tag is one record with a name in each of the nine portal
        languages. The `name` you receive depends on the request language, and `name_locale`
        tells you which language you actually got — with a fallback chain behind it, so `name`
        is never empty even when your language is missing. `translations` carries all of them at
        once for clients that switch languages themselves.

        For meetups in some countries at least one tag is mandatory; the portal enforces that
        when the event is created.

        ## Rate Limiting

        Public endpoints are limited to **60 requests/minute**.

        ## Breaking changes in 2.0.0

        The `Venue` model was removed. Locations now belong to the event itself, as described
        above.

        - `GET|POST /venues`, `PATCH /venues/{venue}`, `GET /my-venues`, `GET /my-venues/{venue}`
          — **gone**, with no replacement endpoint.
        - Course events: `venue_id` and the nested `venue` object are gone. `venue.name` became
          `location`, `venue.city` became `city`, and the street is part of `location`.
        - `GET /courses/{course}` returns `city` and `location` per event instead of `venue`.

        There is no deprecation window, because there is nothing left for the old fields to point
        at. The data itself was carried over: every existing event kept its full address in
        `location`.
        MARKDOWN,
    ],

    'ui' => [
        'title' => 'EINUNDZWANZIG API',
    ],

    'renderer' => 'scalar',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            /*
             * Scalar has no end-user language switcher: the locale is set by config only and
             * localizes the UI chrome (search, navigation, buttons, schema labels) — never the
             * content of the OpenAPI document. Offering a second language would mean shipping a
             * second, separately translated OpenAPI document via Scalar's `sources` option.
             *
             * @see https://scalar.com/products/api-references/localization
             */
            'localization' => [
                'locale' => 'en',
                'direction' => 'ltr',
            ],
            'theme' => 'laravel',
            'proxyUrl' => '',
            'darkMode' => true,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => [
        'Production' => 'https://portal.einundzwanzig.space/api',
        'Local' => 'api',
    ],

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    /*
     * NOTE: `scheme` is intentionally omitted here. Passing a `SecurityScheme` object
     * instance would make the config non-serializable and break `config:cache`/`optimize`
     * (LogicException: value is non-serializable). `MiddlewareAuthSecurityStrategy`
     * defaults to `SecurityScheme::http('bearer')` when no scheme is provided, which is
     * exactly what we want.
     */
    'security_strategy' => [
        MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['auth', 'auth:*'],
        ],
    ],
];
