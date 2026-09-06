<?php

use App\Support\SushiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Sushi\Sushi;
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
 * Clears the guard's own per-process "already reported" registry.
 *
 * SushiCache rate-limits every warning it writes, so without this a test that
 * asserts on a log line would depend on whether an earlier test in the same
 * process already tripped the same key. Reflection rather than a public reset
 * method: the deduplication is an implementation detail, not API.
 */
function forgetSushiCacheReports(): void
{
    $property = new ReflectionProperty(SushiCache::class, 'reported');
    $property->setValue(null, []);
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
    forgetSushiCacheReports();

    // Force one healthy build, then keep a copy: a red run leaves a broken
    // cache file behind, and every later test that renders the sidebar would
    // fail for that reason instead of its own.
    Country::query()->count();

    $this->cachePath = sushiCachePathOfModel(new Country);
    $this->cacheBytes = file_get_contents($this->cachePath);
    $this->cacheMtime = filemtime($this->cachePath);
});

afterEach(function () {
    // One test replaces the cache file with a directory to make the rebuild
    // fail; the restore below would go nowhere otherwise.
    if (is_dir($this->cachePath)) {
        rmdir($this->cachePath);
    }

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

/*
|--------------------------------------------------------------------------
| The seven gate findings on bb275df.
|--------------------------------------------------------------------------
*/

/**
 * A Sushi model whose second row carries a column the first one does not, so
 * the insert fails with a QueryException — the exception class whose message
 * carries the bindings, which is what the log redaction has to strip.
 *
 * It lives in the test suite on purpose: discover() scans app/ and the PSR-4
 * roots of packages depending on Sushi, so nothing here reaches production.
 */
class SushiRedactionFixtureModel extends Model
{
    use Sushi;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected $rows = [
        ['id' => 1, 'label' => 'top-secret-value'],
        ['id' => 2, 'label' => 'x', 'surplus_column' => 'y'],
    ];
}

it('reports a failure without throwing, even when the log sink is broken too', function () {
    // Two faults at once, which is what lets this class of bug survive a review:
    // the rebuild cannot publish (a directory sits where the cache file belongs)
    // AND the logger that would record it is unavailable as well. ensureFresh()
    // is called from a model boot, which has no handler above it, so an
    // exception escaping here 500s every page carrying a Sushi model — #139's
    // failure class through another door.
    unlink($this->cachePath);
    mkdir($this->cachePath);

    Log::shouldReceive('warning')->andThrow(new RuntimeException('log sink unavailable'));

    $blueprint = (new ReflectionClass(Country::class))->newInstanceWithoutConstructor();

    expect(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED);
});

it('takes the rebuild lock on the cache file itself and gives up after a bounded wait', function () {
    // A side-car <cache>.lock is created by whoever warms the cache first —
    // during a deploy that is the deploy user running composer install — and
    // opening it for locking needs write permission. A php-fpm user that
    // differs from the deploy user could never open it again, silently, for the
    // life of the server: storage/ is shared and nothing ever replaces that
    // file. Locking the cache file works instead, and flock() takes a read-only
    // handle.
    //
    // So holding an exclusive lock on the cache FILE has to delay the rebuild.
    // A side-car lock would not notice this handle at all. And the wait has to
    // end: a blocking flock() is not interrupted by max_execution_time, only by
    // php-fpm's request_terminate_timeout, which ends in a 502.
    @unlink($this->cachePath.'.lock');
    touch($this->cachePath, filemtime(sushiDataPathOfModel(new Country)) - 3600);

    $holder = fopen($this->cachePath, 'r');
    flock($holder, LOCK_EX);

    $blueprint = (new ReflectionClass(Country::class))->newInstanceWithoutConstructor();

    $startedAt = microtime(true);
    $status = SushiCache::ensureFresh($blueprint);
    $waited = microtime(true) - $startedAt;

    flock($holder, LOCK_UN);
    fclose($holder);

    expect($status)->toBe(SushiCache::REBUILT)
        ->and($waited)->toBeGreaterThanOrEqual(0.95)
        ->and($waited)->toBeLessThan(5.0)
        ->and(is_file($this->cachePath.'.lock'))->toBeFalse()
        ->and(Country::query()->count())->toBe(249);
});

it('publishes the cache with a fixed mode, not with the umask of the moment', function () {
    // Sushi's in-place write kept whatever mode the file already had. A
    // rename() brings the new file's mode with it, so without an explicit
    // chmod an FPM pool at umask 0000 would publish a world-writable database.
    $umask = umask(0077);

    try {
        touch($this->cachePath, filemtime(sushiDataPathOfModel(new Country)) - 3600);

        forgetBootedModel(Country::class);

        expect(Country::query()->count())->toBe(249);

        clearstatcache();

        expect(fileperms($this->cachePath) & 0777)->toBe(0644);
    } finally {
        umask($umask);
    }
});

it('stops before rebuilding, and reports once, when the model source file is gone', function () {
    // A deployer pruning an old release under a surviving worker: the class is
    // in memory, its file is not. Rebuilding anyway means building every row
    // and then throwing it away at the touch() that needs the source mtime —
    // on every request, on the boot path of nearly every page.
    $file = sys_get_temp_dir().'/sushi_vanishing_'.bin2hex(random_bytes(4)).'.php';
    $class = 'SushiVanishingFixture'.bin2hex(random_bytes(4));

    file_put_contents($file, '<?php class '.$class.' extends Illuminate\Database\Eloquent\Model { use Sushi\Sushi; protected $rows = [["id" => 1, "name" => "one"]]; }');
    require $file;
    unlink($file);

    $blueprint = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $cachePath = sushiCachePathOfModel($blueprint);

    Log::spy();

    try {
        expect(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED)
            ->and(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED)
            ->and(is_file($cachePath))->toBeFalse();

        Log::shouldHaveReceived('warning')->once();
    } finally {
        @unlink($cachePath);
    }
});

it('keeps row values and absolute paths out of the log context', function () {
    // Log context leaves the box verbatim: laravel/nightwatch json_encodes it
    // as-is and redact_payload_fields does not reach it. A QueryException
    // message carries the bindings — up to a whole insert chunk of model rows —
    // plus the absolute database path.
    Log::spy();

    $blueprint = (new ReflectionClass(SushiRedactionFixtureModel::class))->newInstanceWithoutConstructor();
    $cachePath = sushiCachePathOfModel($blueprint);

    try {
        expect(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return ($context['exception'] ?? null) === QueryException::class
                && ! str_contains($context['reason'] ?? '', 'top-secret-value')
                && ! str_contains($context['reason'] ?? '', 'framework/cache')
                && str_starts_with($context['reason'] ?? '', 'SQLSTATE');
        })->once();
    } finally {
        @unlink($cachePath);
    }
});

it('leaves no temporary file behind and publishes a regular file', function () {
    // Invariant, not a regression pin: it held before the temporary file was
    // given an unpredictable name and O_EXCL, and it holds after. The exploit
    // those two close needs a file planted at the exact temporary path, which
    // is no longer constructible — see the note in the report.
    // Compared against what was there before, not against an empty directory:
    // the claim is that this rebuild leaves nothing behind, and an unrelated
    // leftover from another test would otherwise fail it for the wrong reason.
    $before = glob(dirname($this->cachePath).'/*.building');

    touch($this->cachePath, filemtime(sushiDataPathOfModel(new Country)) - 3600);

    forgetBootedModel(Country::class);

    expect(Country::query()->count())->toBe(249);

    clearstatcache();

    expect(glob(dirname($this->cachePath).'/*.building'))->toBe($before)
        ->and(is_link($this->cachePath))->toBeFalse()
        ->and(is_file($this->cachePath))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The third gate pass on 8989fab.
|--------------------------------------------------------------------------
*/

it('names its temporary file unpredictably', function () {
    // This is the whole of F2's defence, and the only thing standing between a
    // future refactor and a reopened hole: PHP's 'x' mode is documented as
    // "equivalent to O_EXCL|O_CREAT" but measured on 8.5.9 it follows a
    // DANGLING symlink and creates the target, where the kernel's own
    // O_CREAT|O_EXCL refuses. So the name — not the open mode — is what stops
    // anyone able to write into the cache directory from picking a victim file.
    // A pid-derived name, which is what this replaced, is guessable in bulk.
    $create = new ReflectionMethod(SushiCache::class, 'createTemporaryFile');
    $first = null;
    $second = null;

    // The creation itself is inside the try: a derived name makes the second
    // call collide and throw, and the first file still has to be cleaned up.
    try {
        $first = $create->invoke(null, $this->cachePath);
        $second = $create->invoke(null, $this->cachePath);

        expect($first)->not->toBe($second)
            ->and(str_contains($first, (string) getmypid()))->toBeFalse()
            ->and(str_contains($second, (string) getmypid()))->toBeFalse()
            ->and(str_ends_with($first, '.building'))->toBeTrue();
    } finally {
        foreach ([$first, $second] as $path) {
            if ($path !== null) {
                @unlink($path);
            }
        }
    }
});

it('refuses to build into a path it cannot create', function () {
    // The other half of createTemporaryFile(): a failed create is an exception,
    // never a silent fall-through to writing somewhere unintended. Provoked
    // through a missing directory rather than an occupied name, because the
    // name is random and cannot be occupied on purpose any more.
    $create = new ReflectionMethod(SushiCache::class, 'createTemporaryFile');

    expect(fn () => $create->invoke(null, $this->cachePath.'/no/such/directory/cache.sqlite'))
        ->toThrow(RuntimeException::class);
});

it('keeps the application paths out of the log context of any exception', function () {
    // Cutting at " (Connection: " only works on a QueryException. Every other
    // exception on this path names an absolute path in plain prose and is short
    // enough that the length cap never reaches it — measured 118 to 191
    // characters. This is the case the F1 test constructs, and it produced two
    // absolute paths in one line.
    Log::spy();

    unlink($this->cachePath);
    mkdir($this->cachePath);

    $blueprint = (new ReflectionClass(Country::class))->newInstanceWithoutConstructor();

    expect(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED);

    Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
        $reason = $context['reason'] ?? '';

        return ! str_contains($reason, base_path())
            && ! str_contains($reason, storage_path())
            && ! str_contains($reason, (string) realpath(storage_path()))
            && str_contains($reason, '<storage>');
    })->once();
});

it('reports a persistent failure once per process, not once per request', function () {
    // The catch-all path used to write on every call while the three keyed
    // conditions were deduplicated — so a persistent local misconfiguration
    // wrote one warning per request, each shipping the paths above off-box.
    Log::spy();

    unlink($this->cachePath);
    mkdir($this->cachePath);

    $blueprint = (new ReflectionClass(Country::class))->newInstanceWithoutConstructor();

    expect(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED)
        ->and(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED)
        ->and(SushiCache::ensureFresh($blueprint))->toBe(SushiCache::FAILED);

    Log::shouldHaveReceived('warning')->once();
});

it('clears a stale lock file left behind by an earlier release', function () {
    // Dropping the side-car from the code does not remove it from a server:
    // storage/ is shared across releases and nothing replaces that file.
    touch($this->cachePath.'.lock');

    try {
        $this->artisan('sushi:warm')
            ->expectsOutputToContain('removed the stale lock file')
            ->assertExitCode(0);

        expect(is_file($this->cachePath.'.lock'))->toBeFalse();
    } finally {
        @unlink($this->cachePath.'.lock');
    }
});
