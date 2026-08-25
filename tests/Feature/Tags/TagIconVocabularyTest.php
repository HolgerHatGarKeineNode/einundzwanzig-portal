<?php

use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The icon whitelist and the two ways it can rot
|--------------------------------------------------------------------------
|
| Flux resolves <flux:icon name="x"> to a Blade component and throws when it
| cannot find one — "Flux component [icon.coin] does not exist" — so an icon
| name is not cosmetic data, it is code that runs. Two things can go wrong and
| neither shows up anywhere else:
|
|   1. a name in config('einundzwanzig.tag_icons') that Flux does not ship
|   2. a name in the seed vocabulary or the database that is not on the list
|
| MEASUREMENT NOTE. Flux inlines every icon as raw SVG; the name it was asked
| for appears nowhere in the output. A test that greps the rendered HTML for
| "microphone" therefore reports "no icon" even where one is drawn — and
| reports it as a pass, which is the worst kind of wrong. The whole reason
| resources/views/livewire/tags/partials/icon.blade.php emits `data-tag-icon`
| is to make the resolved name observable. The last two cases below are that
| hook's own positive and negative control: if they both stopped discriminating,
| every other icon assertion in this suite would silently become vacuous.
|
*/

/**
 * Whether Flux ships a component for this icon name.
 *
 * Both directories are checked: the package's own stubs and the four icons
 * this project published by hand into resources/views/flux/icon.
 */
function fluxIconComponentExists(string $name): bool
{
    return file_exists(base_path("vendor/livewire/flux/stubs/resources/views/flux/icon/{$name}.blade.php"))
        || file_exists(resource_path("views/flux/icon/{$name}.blade.php"));
}

it('lists only icon names that Flux ships a component for', function () {
    $missing = collect(config('einundzwanzig.tag_icons'))
        ->reject(fn (string $name): bool => fluxIconComponentExists($name))
        ->values();

    expect($missing->all())->toBe([], 'not shipped by Flux: '.$missing->implode(', '));
});

it('renders every whitelisted icon without throwing', function () {
    // File existence is a proxy; actually rendering is the thing that either
    // works or takes a page down.
    foreach (config('einundzwanzig.tag_icons') as $name) {
        $html = Blade::render('<flux:icon :icon="$name" variant="mini" />', ['name' => $name]);

        expect($html)->toContain('data-flux-icon');
    }
});

it('keeps the resting subset inside the whitelist', function () {
    $stray = collect(config('einundzwanzig.tag_icons_common'))
        ->diff(config('einundzwanzig.tag_icons'))
        ->values();

    expect($stray->all())->toBe([], 'in tag_icons_common but not in tag_icons: '.$stray->implode(', '));
});

it('holds no duplicates', function () {
    $icons = config('einundzwanzig.tag_icons');

    expect(array_values(array_unique($icons)))->toBe(array_values($icons));
});

it('seeds no icon the whitelist does not know', function () {
    $vocabulary = require database_path('seeders/data/tags.php');

    $stray = collect($vocabulary)
        ->flatten(1)
        ->pluck('icon')
        ->filter()
        ->unique()
        ->diff(config('einundzwanzig.tag_icons'))
        ->values();

    expect($stray->all())->toBe([], 'in database/seeders/data/tags.php but not whitelisted: '.$stray->implode(', '));
});

it('leaves no seeded tag with an icon outside the whitelist', function () {
    $this->seed(TagSeeder::class);

    $diff = Tag::pluck('icon')->unique()->diff(config('einundzwanzig.tag_icons'))->values();

    expect($diff->all())->toBe([], 'stored but not whitelisted: '.$diff->implode(', '));
});

it('marks the icon it actually rendered', function () {
    $html = view('livewire.tags.partials.icon', ['tagIcon' => 'microphone'])->render();

    // The name is observable only because of the hook — the SVG below it carries
    // no trace of "microphone" at all.
    expect($html)->toContain('data-tag-icon="microphone"')
        ->and($html)->not->toContain('data-tag-icon-fallback')
        ->and($html)->toContain('data-flux-icon');
});

it('falls back for a name it does not know and says which one it was', function () {
    // `coin` is one of the ten Font Awesome names the data migration replaced.
    // Rendering it raw would throw; the partial must neither throw nor pretend.
    $html = view('livewire.tags.partials.icon', ['tagIcon' => 'coin'])->render();

    expect($html)->toContain('data-tag-icon="tag"')
        ->and($html)->toContain('data-tag-icon-fallback="coin"');
});

/*
|--------------------------------------------------------------------------
| The two screens that render the value
|--------------------------------------------------------------------------
*/

it('renders every seeded tag through the picker without throwing', function () {
    // Livewire::test() runs the component's Blade, so an unresolvable icon throws
    // here exactly as it would in a browser. The picker is scoped to one type, so
    // covering all ninety-one means asking it once per type.
    $this->seed(TagSeeder::class);
    $this->actingAs(User::factory()->create(['nostr' => null]));

    $drawn = collect(Tag::query()->distinct()->pluck('type'))
        ->sum(function (?string $type): int {
            $html = Livewire::test('tags.picker', ['type' => $type])->assertOk()->html();

            return substr_count($html, 'data-tag-icon=');
        });

    expect($drawn)->toBe(Tag::query()->count());
});

it('answers the moderation route instead of erroring on the layout', function () {
    // This route used to return 500 on every real page load: partials/head renders
    // seo($SEOData) and nothing shared that variable for this component. Only a full
    // request sees it — Livewire::test() renders the component without its layout.
    $this->seed(TagSeeder::class);

    $this->actingAs(User::factory()->create([
        'nostr' => config('einundzwanzig.tag_editors')[0],
    ]));

    $this->get('/de/tags/moderation')->assertOk();

    $this->actingAs(User::factory()->create(['nostr' => null]));

    $this->get('/de/tags/moderation')->assertForbidden();
});

it('shows a stored icon it cannot resolve instead of dying on it', function () {
    $this->seed(TagSeeder::class);

    $tag = Tag::query()->where('type', 'meetup_event')->firstOrFail();
    $tag->newQuery()->whereKey($tag->id)->update(['icon' => 'coin']);

    $this->actingAs(User::factory()->create([
        'nostr' => config('einundzwanzig.tag_editors')[0],
    ]));

    $response = $this->get('/de/tags/moderation')->assertOk();

    // Named, not swallowed — a fallback nobody is told about is how ten Font Awesome
    // names survived unnoticed in the first place.
    expect($response->getContent())
        ->toContain('coin — nicht auflösbar')
        ->toContain('data-tag-icon-fallback="coin"');
});
