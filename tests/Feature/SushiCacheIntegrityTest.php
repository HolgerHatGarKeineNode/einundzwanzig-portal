<?php

use App\Support\SushiCache;
use Illuminate\Database\Eloquent\Model;
use WW\Countries\Models\Country;

/*
|--------------------------------------------------------------------------
| Issue #139 — a Sushi cache file must never be trusted on its mtime alone.
|--------------------------------------------------------------------------
|
| Sushi decides freshness with one comparison (Sushi.php):
|
|     case file_exists($cachePath) && filemtime($dataPath) <= filemtime($cachePath):
|         $states['cache-file-found-and-up-to-date']();   // just connects
|
| and rebuilds non-atomically:
|
|     file_put_contents($cachePath, '');   // zero bytes, mtime = now
|     static::setSqliteConnection($cachePath);
|     $instance->migrate();                // creates the table, inserts the rows
|     touch($cachePath, filemtime($dataPath));
|
| Between line 1 and line 4 the file exists with mtime = now, which is greater
| than the model file's mtime. A concurrent request therefore takes the
| "up to date" branch, connects to a zero-byte database and 500s with
| "no such table: countries". If the rebuilding process dies inside migrate(),
| that state is not transient — the truncated file keeps its future mtime and
| every later request reads an empty database until the next deploy.
|
| The tests below reproduce exactly those on-disk states and assert the model
| still answers, and that a stale cache is replaced by rename() rather than
| truncated in place. Without the fix the first three fail with
| QueryException "no such table: countries" or on an unchanged inode.
*/

/**
 * Sushi keeps the cache path behind protected methods. Reading them through a
 * bound closure keeps the test honest: it asks the trait where the file is
 * rather than re-deriving the name and passing even if the derivation drifts.
 */
function sushiCachePathOfModel(Model $model): string
{
    return Closure::bind(fn () => $this->sushiCachePath(), $model, $model::class)();
}

/**
 * The file whose mtime Sushi compares the cache against — the model's own
 * source file, which is what a zero-downtime deploy gives a fresh mtime.
 */
function sushiDataPathOfModel(Model $model): string
{
    return Closure::bind(fn () => $this->sushiCacheReferencePath(), $model, $model::class)();
}

/**
 * Drops one model out of Eloquent's booted registry so the next instantiation
 * runs bootSushi again — the moment at which the cache file is inspected.
 * Model::clearBootedModels() would do it for every model in the process and
 * would also wipe every registered global scope.
 */
function forgetBootedModel(string $model): void
{
    foreach (['booted', 'bootedCallbacks'] as $name) {
        $property = new ReflectionProperty(Model::class, $name);
        $values = $property->getValue();
        unset($values[$model]);
        $property->setValue(null, $values);
    }
}

beforeEach(function () {
    // Force one healthy build, then keep a copy: a red run leaves a broken
    // cache file behind, and every later test that renders the sidebar would
    // fail for that reason instead of its own.
    Country::query()->count();

    $this->cachePath = sushiCachePathOfModel(new Country);
    $this->cacheBytes = file_get_contents($this->cachePath);
    $this->cacheMtime = filemtime($this->cachePath);
});

afterEach(function () {
    file_put_contents($this->cachePath, $this->cacheBytes);
    touch($this->cachePath, $this->cacheMtime);

    forgetBootedModel(Country::class);
});

it('rebuilds a truncated cache file whose mtime claims it is up to date', function () {
    file_put_contents($this->cachePath, '');
    touch($this->cachePath, time() + 3600);

    forgetBootedModel(Country::class);

    expect(filesize($this->cachePath))->toBe(0)
        ->and(Country::query()->count())->toBe(249);
});

it('rebuilds a non-empty cache file whose table is missing', function () {
    // A process killed inside migrate() can leave a file that is no longer
    // empty but has no countries table yet — the mtime check waves it through
    // just the same.
    unlink($this->cachePath);
    $pdo = new PDO('sqlite:'.$this->cachePath);
    $pdo->exec('create table half_migrated (id integer)');
    $pdo = null;
    touch($this->cachePath, time() + 3600);

    forgetBootedModel(Country::class);

    expect(filesize($this->cachePath))->toBeGreaterThan(0)
        ->and(Country::query()->count())->toBe(249);
});

it('publishes a rebuilt cache file instead of truncating the live one', function () {
    // A deploy leaves exactly this state: the cache file is older than the
    // model file, so Sushi calls it stale. Its own rebuild truncates the file
    // in place — same inode, zero bytes, for as long as the insert takes.
    // Publishing by rename() gives the path a new inode, and a concurrent
    // reader keeps the complete previous database until the moment it flips.
    $inode = fileinode($this->cachePath);
    touch($this->cachePath, filemtime(sushiDataPathOfModel(new Country)) - 3600);

    forgetBootedModel(Country::class);

    expect(Country::query()->count())->toBe(249);

    clearstatcache();

    expect(fileinode($this->cachePath))->not->toBe($inode)
        ->and(filemtime($this->cachePath))->toBe(filemtime(sushiDataPathOfModel(new Country)));
});

it('discovers the Sushi-backed models instead of naming one', function () {
    expect(SushiCache::discover())->toContain(Country::class);
});

it('warms every discovered model and reports what the table holds', function () {
    $this->artisan('sushi:warm')
        ->expectsOutputToContain('WW\Countries\Models\Country: 249 row(s)')
        ->assertExitCode(0);
});

it('rebuilds a broken cache while warming up', function () {
    file_put_contents($this->cachePath, '');
    touch($this->cachePath, time() + 3600);

    forgetBootedModel(Country::class);

    $this->artisan('sushi:warm')
        ->expectsOutputToContain('WW\Countries\Models\Country: 249 row(s) (rebuilt).')
        ->assertExitCode(0);
});

it('exits 0 when a model cannot be warmed at all', function () {
    // A warm-up failure must never break `composer install`, which is where
    // post-autoload-dump runs this.
    $this->artisan('sushi:warm', ['model' => ['App\Models\NoSuchSushiModel']])
        ->expectsOutputToContain('no such class')
        ->assertExitCode(0);
});

it('runs the warm-up during composer install, next to package:discover', function () {
    $scripts = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($scripts['scripts']['post-autoload-dump'])
        ->toContain('@php artisan package:discover --ansi')
        ->toContain('@php artisan sushi:warm --ansi');
});
