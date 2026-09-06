<?php

use Illuminate\Support\Facades\Blade;
use Livewire\Compiler\Parser\SingleFileParser;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Issue #132 — a formatter may not silently break a single-file component
|--------------------------------------------------------------------------
|
| The 52 Blade files under resources/views that carry `class extends
| Component` (the issue calls them Volt SFCs; on Livewire 4 they are
| single-file components) are now formatted by Pint through explicit paths,
| because Pint's directory walk drops *.blade.php. Formatting a Blade file
| is riskier than formatting a PHP file: this repo has a recorded case where
| moving lines around an inline `@php(...)` directive took a whole route down
| (see the long comment in livewire/meetups/landingpage.blade.php). pint.json
| switches off every fixer that moves a line; this file is the check that the
| result still compiles.
|
| WHAT IS ASSERTED, AND WHY IT IS TWO THINGS
|
|  1. The compiled template parses. BladeCompiler::storePhpBlocks() matches
|     /(?<!@)@php(.*?)@endphp/s, so an inline `@php(...)` pairs with the NEXT
|     `@endphp` anywhere below it and swallows everything in between. Where
|     that leaves a conditional unclosed, the compiled view is invalid PHP:
|     "syntax error, unexpected end of file, expecting elseif ... endif".
|
|  2. No `@php`/`@endphp` survives into the compiled output. Measured while
|     writing this file: the parse check ALONE is not enough. The swallowed
|     body is restored as `<?php{$body}?>`, and when the body starts with a
|     parenthesis the result is `<?php(...` — which PHP does not recognise as
|     an opening tag at all. The whole broken region then degrades to inline
|     HTML and the template parses cleanly while rendering garbage. The
|     surviving literal `@php` is what gives it away in that case, and one of
|     the two controls below is exactly that shape.
|
| Both controls are part of the run, not a one-off: a check that cannot fail
| is worth nothing, and neither symptom is visible in the source.
|
| SCOPE. This compiles; it does not render. A component is taken through the
| Livewire single-file parser and the Blade compiler — the two steps that turn
| the file on disk into the PHP that runs — without booting the component,
| which would need per-component mount parameters and authentication. What is
| out of reach here is a runtime error inside a component method; what is
| covered is every way the file can stop being valid PHP.
*/

/**
 * Every Livewire single-file component under resources/views.
 *
 * Discovery repeats the issue's own criterion (`class extends Component`)
 * rather than asking Livewire, so a component in a directory nobody mounted
 * yet is still checked — that file is one `Volt::mount()` away from being live
 * and is formatted by the same command either way.
 *
 * @return array<string, string> path relative to the project root => absolute path
 */
function livewireSingleFileComponents(): array
{
    $components = [];

    $files = (new Finder)
        ->files()
        ->in(resource_path('views'))
        ->name('*.blade.php');

    foreach ($files as $file) {
        if (preg_match('/\bclass\s+extends\s+Component\b/', $file->getContents()) === 1) {
            $components[str_replace(base_path().'/', '', $file->getRealPath())] = $file->getRealPath();
        }
    }

    ksort($components);

    return $components;
}

/**
 * Compiles a Blade template and reports how it is broken, or null.
 *
 * @return string|null the parse error, or the surviving directive, or null
 */
function bladeCompilationDefect(string $template): ?string
{
    $compiled = Blade::compileString($template);

    try {
        token_get_all($compiled, TOKEN_PARSE);
    } catch (ParseError $e) {
        return 'parse error: '.$e->getMessage();
    }

    // A literal @php/@endphp in the OUTPUT means the compiler never converted
    // it — the directive was swallowed by an earlier inline @php(...). An
    // escaped @@php would land here too; it compiles to a literal @php on
    // purpose. None of the 52 components uses that escape (measured 2026-09-06),
    // so the check stays this simple until one does.
    if (preg_match('/@(php|endphp)\b/', $compiled) === 1) {
        return 'the compiled output still contains a literal @php/@endphp directive';
    }

    return null;
}

/**
 * The source Blade compiles for a single-file component: the parser splits the
 * file into a class and a view, and only the view goes through Blade.
 *
 * @return array{view: string, class: string}
 */
function singleFileComponentParts(string $path): array
{
    $parser = SingleFileParser::parse(app('livewire.compiler'), $path);

    return [
        'view' => $parser->generateViewContents(),
        // The file name is only written into a #[\Livewire\Attributes\View]
        // style reference; nothing here reads it back.
        'class' => $parser->generateClassContents('single-file-components-compile-test.blade.php'),
    ];
}

it('finds the single-file components it is meant to guard', function () {
    $components = livewireSingleFileComponents();

    // Without this, a discovery that silently returns nothing would report a
    // green run over zero files — the same fail-open shape as the Pint blind
    // spot this test was written for.
    expect($components)->not->toBeEmpty();

    // Deliberately array_key_exists() rather than expect()->toHaveKey(): that
    // matcher takes ($key, $value, $message), so a message passed as the second
    // argument is silently read as the expected value.
    expect(array_key_exists('resources/views/livewire/meetups/landingpage.blade.php', $components))->toBeTrue(
        'The component carrying the documented @php(...) trap is no longer discovered.'
    );
});

it('compiles every single-file component into valid PHP', function () {
    $broken = [];

    foreach (livewireSingleFileComponents() as $relativePath => $path) {
        $parts = singleFileComponentParts($path);

        $defect = bladeCompilationDefect($parts['view']);

        if ($defect !== null) {
            $broken[$relativePath] = $defect;
        }

        try {
            token_get_all($parts['class'], TOKEN_PARSE);
        } catch (ParseError $e) {
            // The class half is the part Pint reformats, so it gets its own
            // report line rather than being folded into the view's.
            $broken[$relativePath.' (component class)'] = 'parse error: '.$e->getMessage();
        }
    }

    expect($broken)->toBe([], 'components that no longer compile: '.json_encode($broken, JSON_PRETTY_PRINT));
});

it('keeps the fixer list in CLAUDE.md and pint.json in step', function () {
    // The first attempt at #132 shipped a CLAUDE.md sentence naming two fixers
    // as line-movers that are not — measured at their own preset configuration,
    // both only edit inside a line. A prose list of switched-off fixers rots the
    // moment someone edits pint.json, and it rots silently, in the one file whose
    // whole job is to be believed later. Both directions are asserted.
    $disabled = collect(json_decode(file_get_contents(base_path('pint.json')), true)['rules'])
        ->filter(fn (mixed $value): bool => $value === false)
        ->keys()
        ->sort()
        ->values()
        ->all();

    $claudeMd = file_get_contents(base_path('CLAUDE.md'));

    // Only the three bullets that enumerate the switched-off fixers, so a fixer
    // named elsewhere in the prose (as `class_attributes_separation` is, for the
    // opposite reason) is not mistaken for a claim that it is off.
    preg_match_all(
        '/^  - \*\*(?:Permute existing lines|Move code between lines|Inserts rather than moves):\*\*.*$/m',
        $claudeMd,
        $matches
    );

    expect($matches[0])->toHaveCount(3, 'The three fixer bullets in CLAUDE.md have been renamed or removed; this guard can no longer find them.');

    // The bullets are kept as bare enumerations for exactly this reason: any
    // prose in them would put non-fixer words into the comparison, and an
    // exclusion list would be the next thing to rot.
    preg_match_all('/`([a-z][a-z0-9_]+)`/', implode("\n", $matches[0]), $named);
    $documented = collect($named[1])
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($documented)->toBe(
        $disabled,
        'CLAUDE.md and pint.json disagree about which fixers are switched off. '
        ."pint.json: \n- ".implode("\n- ", $disabled)."\nCLAUDE.md: \n- ".implode("\n- ", $documented)
    );
});

it('reports an inline @php(...) that swallows a following block and unbalances a conditional', function () {
    // The @endif sits OUTSIDE the swallowed region, so the compiled view keeps
    // an unclosed conditional. This is the shape that took meetups/landingpage
    // down on 2026-09-03.
    $template = <<<'BLADE'
        <div>
            @if ($ready)
                @php($label = 'ready')
                <p>{{ $label }}</p>
            @endif

            @php
                $footer = 'done';
            @endphp

            <span>{{ $footer }}</span>
        </div>
        BLADE;

    expect(bladeCompilationDefect($template))
        ->toStartWith('parse error: syntax error, unexpected end of file');
});

it('reports an inline @php(...) that swallows a following block without breaking the parse', function () {
    // Same defect, no unbalanced conditional: the swallowed body opens with a
    // parenthesis, `<?php(` is not an opening tag, and the region silently
    // degrades to inline HTML. The parse check is blind here.
    $template = <<<'BLADE'
        <div>
            @php($ready = true)

            @if ($ready)
                <p>ready</p>
            @endif

            @php
                $label = 'done';
            @endphp

            <span>{{ $label }}</span>
        </div>
        BLADE;

    expect(bladeCompilationDefect($template))
        ->toBe('the compiled output still contains a literal @php/@endphp directive');
});

it('passes the same two templates once the inline directive is written as a block', function () {
    // The controls above only mean something if their healthy twins are clean —
    // otherwise they could be failing for any other reason.
    $unbalanced = <<<'BLADE'
        <div>
            @if ($ready)
                @php
                    $label = 'ready';
                @endphp
                <p>{{ $label }}</p>
            @endif

            @php
                $footer = 'done';
            @endphp

            <span>{{ $footer }}</span>
        </div>
        BLADE;

    $degraded = <<<'BLADE'
        <div>
            @php
                $ready = true;
            @endphp

            @if ($ready)
                <p>ready</p>
            @endif

            @php
                $label = 'done';
            @endphp

            <span>{{ $label }}</span>
        </div>
        BLADE;

    expect(bladeCompilationDefect($unbalanced))->toBeNull();
    expect(bladeCompilationDefect($degraded))->toBeNull();
});
