<?php

use Laravel\Boost\Contracts\SupportsGuidelines;
use Laravel\Boost\Install\Agents\Agent;

/*
|--------------------------------------------------------------------------
| Guard against #120 regressing: this project keeps ONE guidelines file,
| CLAUDE.md, and AGENTS.md must not come back.
|
| AGENTS.md was a second, full copy of the <laravel-boost-guidelines> block,
| written by whichever agent defaulted to that filename. Nothing kept it in
| step with CLAUDE.md: it still carried the .ai/rules paragraph that #60
| removed, and it carried the Browser-suite warning INSIDE its Boost block,
| the very defect #83 was filed for. Two files stay in step only as long as
| somebody remembers both, and nobody did.
|
| Deleting the file is not enough. Only Claude Code defaults its guidelines
| to CLAUDE.md; OpenCode and Junie (PhpStorm) both default to AGENTS.md, so
| an agent left on its default recreates the file at the next boost:install
| and the deletion silently undoes itself. Both halves are therefore
| asserted: the file is absent, AND no configured agent still aims at it.
|--------------------------------------------------------------------------
*/

/**
 * The single file every agent's guidelines must land in.
 */
const BOOST_GUIDELINES_FILE = 'CLAUDE.md';

/**
 * Agent names as boost.json spells them, mapped to the key BoostManager
 * registers them under. boost.json says "phpstorm"; the registry knows that
 * agent as "junie" (Laravel\Boost\Install\Agents\Junie), so the name in
 * boost.json resolves to nothing on its own — Boost drops it from the
 * defaults instead of reporting it. Checking it anyway is deliberate: an
 * unresolvable name is exactly the case where a stale default would go
 * unnoticed.
 *
 * @var array<string, string>
 */
const BOOST_AGENT_NAME_ALIASES = [
    'phpstorm' => 'junie',
];

/**
 * The agent names enabled in boost.json, translated to registry keys.
 *
 * @return array<int, string>
 */
function enabledBoostAgentNames(): array
{
    $config = json_decode(file_get_contents(base_path('boost.json')), true);

    expect($config)->toBeArray('boost.json is missing or is not valid JSON.');

    $names = $config['agents'] ?? [];

    expect($names)->not->toBeEmpty('boost.json lists no agents; this guard would assert nothing.');

    return array_map(
        fn (string $name): string => BOOST_AGENT_NAME_ALIASES[$name] ?? $name,
        $names
    );
}

it('has no AGENTS.md', function () {
    // is_link() as well as file_exists(): a symlink pointing nowhere is still
    // a file another tool will read and Boost will happily write through.
    $path = base_path('AGENTS.md');

    expect(file_exists($path) || is_link($path))->toBeFalse(
        'AGENTS.md is back. It is a second copy of the Boost guidelines that nothing keeps '
        .'in step with '.BOOST_GUIDELINES_FILE.'. Delete it, and check which agent recreated it.'
    );
});

it('points every enabled agent at the one guidelines file', function () {
    foreach (enabledBoostAgentNames() as $name) {
        $agent = Agent::fromName($name);

        expect($agent)->not->toBeNull(
            "boost.json enables the agent \"{$name}\", which Boost does not register under that "
            .'name. This guard cannot tell where its guidelines would be written; add it to '
            .'BOOST_AGENT_NAME_ALIASES with the key BoostManager uses.'
        );

        if (! $agent instanceof SupportsGuidelines) {
            continue;
        }

        expect($agent->guidelinesPath())->toBe(
            BOOST_GUIDELINES_FILE,
            "The agent \"{$name}\" writes its guidelines somewhere other than "
            .BOOST_GUIDELINES_FILE.'. Left on its default it recreates AGENTS.md at the next '
            .'boost:install; pin it via boost.agents.'.$name.'.guidelines_path in config/boost.php.'
        );
    }
});

it('tells OpenCode to read the one guidelines file', function () {
    // config/boost.php only decides where boost:install WRITES. What OpenCode
    // READS is its own config, and without an explicit "instructions" entry
    // that rests on its undocumented defaults. Key verified against
    // https://opencode.ai/config.json ($defs.Config.properties.instructions,
    // array of strings, fetched 2026-09-05) — the schema opencode.json names.
    $config = json_decode(file_get_contents(base_path('opencode.json')), true);

    expect($config)->toBeArray('opencode.json is missing or is not valid JSON.');

    // Deliberately in_array() rather than expect()->toContain(): Pest's
    // toContain() is variadic, so a failure message passed as a second
    // argument becomes a second needle the value would have to contain.
    // Same reason as BoostProjectRulesDisabledTest.
    expect(in_array(BOOST_GUIDELINES_FILE, $config['instructions'] ?? [], true))->toBeTrue(
        'opencode.json must name '.BOOST_GUIDELINES_FILE.' in its "instructions" array. Without '
        .'it, OpenCode reads whatever its defaults point at — which is how AGENTS.md earned its '
        .'keep in the first place.'
    );
});
