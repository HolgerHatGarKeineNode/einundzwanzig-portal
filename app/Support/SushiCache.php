<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use Sushi\Sushi;
use Throwable;

/**
 * Keeps the on-disk SQLite cache of a Sushi-backed model complete and fresh
 * *before* the trait itself looks at it (Issue #139).
 *
 * Sushi decides freshness on one mtime comparison and rebuilds in place:
 *
 *     file_put_contents($cachePath, '');   // zero bytes, mtime = now
 *     static::setSqliteConnection($cachePath);
 *     $instance->migrate();                // creates the table, inserts the rows
 *     touch($cachePath, filemtime($dataPath));
 *
 * Two consequences, both measured in production on 2026-09-06:
 *
 *  - During the rebuild the file exists, is empty and carries mtime = now,
 *    which is *greater* than the model file's mtime. Concurrent requests take
 *    Sushi's "cache-file-found-and-up-to-date" branch, connect to an empty
 *    database and fail with "no such table: countries".
 *  - If the rebuilding process dies inside migrate(), that state survives. The
 *    truncated file keeps its future mtime and every later request reads an
 *    empty database until the next deploy.
 *
 * The fix does not patch Sushi. It makes sure the file Sushi finds is always
 * complete and always newer than the model file, by rebuilding it into a
 * temporary file and publishing it with a single rename(). A reader therefore
 * sees either the previous complete database or the new one, never a partial
 * one, and mtime never lies about content.
 *
 * Freshness is not taken on trust: the row count in the file has to match the
 * model's row set. That is the half that heals the permanent failure — a file
 * that exists but is empty, half-migrated or truncated is rebuilt instead of
 * being read as up to date.
 */
final class SushiCache
{
    /**
     * The model does not cache to disk at all (no $rows property), or the cache
     * directory is missing or read-only. Sushi falls back to an in-memory
     * database in both cases, which cannot go stale.
     */
    public const SKIPPED = 'skipped';

    /** The file on disk is complete and newer than the model file. */
    public const FRESH = 'fresh';

    /** The file was rebuilt and published atomically. */
    public const REBUILT = 'rebuilt';

    /**
     * The guard did not run to completion and Sushi's own lazy path takes over
     * unchanged.
     *
     * That is the state of affairs before this class existed, which is not the
     * same as a safe one. Besides the truncated file — which fails loudly with
     * "no such table" — Sushi's mtime rule also accepts a *partially inserted*
     * cache, and that one serves a short country list without any error at all.
     * Every FAILED therefore re-arms both, the silent variant included.
     */
    public const FAILED = 'failed';

    /**
     * How long a rebuild waits for a lock held by another process before it
     * gives up and rebuilds unsynchronised. A rebuild of the only Sushi model
     * here takes well under a millisecond, so this is pure headroom.
     */
    private const LOCK_TIMEOUT_SECONDS = 1.0;

    private const LOCK_RETRY_MICROSECONDS = 20000;

    /** Upper bound for what an exception message may contribute to a log line. */
    private const REPORTED_REASON_LIMIT = 200;

    /**
     * @var array<class-string, bool>
     */
    private static array $usesSushi = [];

    /**
     * Problems already reported in this process, so that a condition on the
     * boot path of nearly every page cannot turn into a log amplifier.
     *
     * @var array<string, true>
     */
    private static array $reported = [];

    /**
     * Memoised: this runs on every Eloquent model boot in the process, not just
     * on the Sushi ones.
     *
     * @param  class-string  $model
     */
    public static function isSushiModel(string $model): bool
    {
        return self::$usesSushi[$model] ??= in_array(Sushi::class, class_uses_recursive($model), true);
    }

    /**
     * Guarantee that the model's cache file is complete and fresh, rebuilding
     * it atomically if it is not.
     *
     * Never throws — including out of its own error handling. The caller is a
     * model boot with no handler above it, so a failure here has to leave
     * Sushi's own behaviour intact rather than take the request down.
     *
     * @return self::SKIPPED|self::FRESH|self::REBUILT|self::FAILED
     */
    public static function ensureFresh(Model $model): string
    {
        $class = $model::class;

        if (! self::isSushiModel($class)) {
            return self::SKIPPED;
        }

        try {
            if (! self::readFromSushi($model, 'sushiShouldCache')) {
                return self::SKIPPED;
            }

            $directory = self::readFromSushi($model, 'sushiCacheDirectory');

            if (! is_string($directory) || ! is_dir($directory) || ! is_writable($directory)) {
                return self::SKIPPED;
            }

            $cachePath = self::readFromSushi($model, 'sushiCachePath');
            $dataModifiedAt = self::modifiedAt(self::readFromSushi($model, 'sushiCacheReferencePath'));

            if ($dataModifiedAt === null) {
                // The model's own source file is gone — a release directory
                // pruned while a worker still holds the class in memory. There
                // is nothing left to compare the cache against and nothing to
                // stamp a rebuild with, so this stops here instead of building
                // every row only to throw the result away at the touch().
                //
                // The page does not survive this either way: Sushi reads that
                // same mtime one step later, in its own switch, and the boot
                // dies with "ErrorException: filemtime(): stat failed for …"
                // (measured). Only patching Sushi could change that. What is in
                // reach is not wasting the work and not writing one log line per
                // request while it lasts.
                self::reportOnce(
                    $class.':source-file-gone',
                    'The source file of Sushi model '.$class.' is gone; its cache can neither be verified nor stamped.'
                );

                return self::FAILED;
            }

            if (self::isComplete($model, $cachePath, $dataModifiedAt)) {
                return self::FRESH;
            }

            return self::rebuildExclusively($model, $cachePath, $dataModifiedAt);
        } catch (Throwable $exception) {
            self::report($class, $exception);

            return self::FAILED;
        }
    }

    /**
     * Every Sushi-backed model of this application and of the packages that
     * declare a dependency on calebporzio/sushi.
     *
     * Discovery, rather than a hardcoded list, so that a second Sushi model
     * added by a package upgrade is warmed without anyone remembering to add
     * it.
     *
     * A file only qualifies when it mentions Sushi *and* declares a class of
     * its own name. The first half is what keeps this away from files whose
     * name is not a class — app/helpers.php is dropped there, not by the class
     * filter. (It would survive a second include unharmed either way: all seven
     * of its functions are function_exists-guarded. The filters are cheapness
     * and defence in depth, not a fix for a redeclare fatal.) The second half
     * is what keeps class_exists() from autoloading half the application.
     *
     * A model that inherits the trait from a parent without naming Sushi
     * anywhere in its own file is therefore not discovered here; it is still
     * healed at runtime, where the guard hooks the model boot itself.
     *
     * @return array<int, class-string<Model>>
     */
    public static function discover(): array
    {
        $models = [];

        foreach (self::searchRoots() as [$namespace, $directory]) {
            foreach (self::classCandidatesIn($namespace, $directory) as $class) {
                if (! class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                if (self::isSushiModel($class)) {
                    $models[$class] = $class;
                }
            }
        }

        $models = array_values($models);
        sort($models);

        return $models;
    }

    /**
     * @return array<int, array{0: string, 1: string}> [PSR-4 prefix, directory]
     */
    private static function searchRoots(): array
    {
        $roots = [['App\\', app_path()]];

        $installed = base_path('vendor/composer/installed.json');

        if (! is_file($installed)) {
            return $roots;
        }

        $manifest = json_decode((string) file_get_contents($installed), true);
        $packages = $manifest['packages'] ?? $manifest ?? [];

        foreach ($packages as $package) {
            if (! array_key_exists('calebporzio/sushi', $package['require'] ?? [])) {
                continue;
            }

            $installPath = dirname($installed).'/'.($package['install-path'] ?? '');

            foreach ($package['autoload']['psr-4'] ?? [] as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    $directory = realpath($installPath.'/'.$path);

                    if ($directory !== false) {
                        $roots[] = [$namespace, $directory];
                    }
                }
            }
        }

        return $roots;
    }

    /**
     * @return array<int, class-string>
     */
    private static function classCandidatesIn(string $namespace, string $directory): array
    {
        $candidates = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            $basename = $file->getBasename('.php');

            if (! str_contains($contents, 'Sushi')) {
                continue;
            }

            if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+'.preg_quote($basename, '/').'\b/mi', $contents) !== 1) {
                continue;
            }

            $relative = trim(str_replace($directory, '', $file->getPath()), DIRECTORY_SEPARATOR);
            $suffix = $relative === '' ? '' : str_replace(DIRECTORY_SEPARATOR, '\\', $relative).'\\';

            $candidates[] = $namespace.$suffix.$basename;
        }

        return $candidates;
    }

    /**
     * The cache file is only complete when it is newer than the model file
     * (Sushi's own staleness rule) *and* actually holds the model's rows.
     *
     * The second half is the point: a zero-byte or half-migrated file passes
     * the mtime test and fails every query made against it.
     */
    private static function isComplete(Model $model, string $cachePath, int $dataModifiedAt): bool
    {
        $cacheModifiedAt = self::modifiedAt($cachePath);

        if ($cacheModifiedAt === null || $cacheModifiedAt < $dataModifiedAt || filesize($cachePath) === 0) {
            return false;
        }

        return self::countRowsIn($cachePath, $model->getTable()) === count($model->getRows());
    }

    /**
     * @return int|null null when the path is not a readable regular file — a
     *                  deleted release directory under a surviving worker being
     *                  the case that matters
     */
    private static function modifiedAt(string $path): ?int
    {
        if (! is_file($path)) {
            return null;
        }

        $modifiedAt = @filemtime($path);

        return $modifiedAt === false ? null : $modifiedAt;
    }

    /**
     * @return int|null null when the file is not a readable database, or has
     *                  no such table — both mean "rebuild it"
     */
    private static function countRowsIn(string $path, string $table): ?int
    {
        try {
            $connection = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $count = $connection->query('select count(*) from "'.str_replace('"', '""', $table).'"')->fetchColumn();

            return (int) $count;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Only one process rebuilds; the others wait on the lock and then find the
     * finished file on the re-check.
     *
     * Correctness does not hang on the lock — every process builds into its own
     * temporary file and publishes it whole — but without it, every request
     * that arrives during a rebuild would redundantly rebuild every row of its
     * own, which is precisely the burst the deploy window produces.
     */
    private static function rebuildExclusively(Model $model, string $cachePath, int $dataModifiedAt): string
    {
        $lock = self::lock($model::class, $cachePath);

        try {
            // Another process may have finished the rebuild while this one waited.
            if (self::isComplete($model, $cachePath, $dataModifiedAt)) {
                return self::FRESH;
            }

            self::rebuild($model, $cachePath, $dataModifiedAt);

            return self::REBUILT;
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Take the rebuild lock on the cache file itself.
     *
     * NOT on a side-car ".lock" file: whoever warms the cache first creates it,
     * during a deploy that is the deploy user running `composer install`, and
     * opening a file for locking with fopen(…, 'c') needs write permission. A
     * php-fpm user that differs from the deploy user could then never open it
     * again — and because storage/ is the shared symlink and nothing ever
     * replaces that file, the lock would stay dead for the life of the server,
     * with no symptom inside the application beyond the very herd it exists to
     * prevent. The cache file is replaced on every rebuild, and flock() is
     * happy with a read-only handle, which every consumer of the cache has by
     * definition.
     *
     * @param  class-string  $class
     * @return resource|null null when no lock could be taken; the rebuild then
     *                       runs unsynchronised, which costs duplicated work but
     *                       stays correct
     */
    private static function lock(string $class, string $cachePath): mixed
    {
        if (! is_file($cachePath)) {
            // Cold start: there is no file to lock on, and no reader that could
            // be handed a truncated one either.
            return null;
        }

        $handle = @fopen($cachePath, 'r');

        if ($handle === false) {
            self::reportOnce(
                $class.':cache-unreadable-for-locking',
                'The Sushi cache of '.$class.' could not be opened for locking; its rebuild runs unsynchronised.'
            );

            return null;
        }

        $deadline = microtime(true) + self::LOCK_TIMEOUT_SECONDS;

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }

            usleep(self::LOCK_RETRY_MICROSECONDS);
        } while (microtime(true) < $deadline);

        // A blocking flock() has no deadline of its own. max_execution_time does
        // not interrupt it — measured: a process blocked on a 4s holder under
        // `php -d max_execution_time=2` returned true after 4.00s — and only
        // php-fpm's request_terminate_timeout ends it, with a 502. Giving up
        // after a bounded wait and rebuilding unsynchronised is the cheaper
        // failure.
        fclose($handle);

        self::reportOnce(
            $class.':lock-timeout',
            'Waited '.self::LOCK_TIMEOUT_SECONDS.'s for the Sushi cache lock of '.$class.'; rebuilding unsynchronised.'
        );

        return null;
    }

    /**
     * Build the database in a temporary file and publish it with rename(),
     * which is atomic within a directory. Nothing ever observes the cache path
     * in a truncated state, so no concurrent reader can be handed an empty
     * database — the failure mode of #139.
     *
     * The rows are inserted through the connection instead of Sushi's own
     * migrate(), which calls static::insert() and would instantiate the model.
     * Laravel 13 forbids that while the model is booting, and the model boot is
     * where this guard runs. The five statements below mirror
     * {@see Sushi::migrate()} of calebporzio/sushi **v2.5.4** — keep them
     * in sync on every upgrade of that package. A breaking change upstream is
     * caught by the test suite; an additive one (a new step inside migrate())
     * would be silently skipped here.
     *
     * While this runs, the model's Sushi connection points at the unpublished
     * temporary file. Nothing else queries the model in that window today,
     * because getRows() of the only Sushi model in this application is a static
     * array. A model whose getRows() reads the database or calls out would break
     * that assumption: it would query through this connection and see the
     * half-built file.
     */
    private static function rebuild(Model $model, string $cachePath, int $dataModifiedAt): void
    {
        $temporaryPath = self::createTemporaryFile($cachePath);

        self::connect($model::class, $temporaryPath);

        try {
            $rows = $model->getRows();
            $table = $model->getTable();

            if ($rows === []) {
                $model->createTableWithNoData($table);
            } else {
                $model->createTable($table, $rows[0]);
            }

            foreach (array_chunk($rows, $model->getSushiInsertChunkSize()) as $chunk) {
                $model->getConnection()->table($table)->insert($chunk);
            }

            // Close the handle first: the file has to be flushed and complete
            // at the moment rename() publishes it.
            $model->getConnection()->disconnect();

            // Without this the published file carries whatever the rebuilding
            // process's umask allowed — measured 0644/0600/0664 under umask
            // 022/0077/0002, so an FPM pool at umask 0000 would publish a
            // world-writable database. Sushi's in-place write kept the mode the
            // file already had; a rename() brings the new file's mode with it.
            chmod($temporaryPath, 0644);

            touch($temporaryPath, $dataModifiedAt);

            if (! rename($temporaryPath, $cachePath)) {
                throw new RuntimeException('Could not publish the rebuilt Sushi cache at '.$cachePath.'.');
            }
        } catch (Throwable $exception) {
            @unlink($temporaryPath);

            throw $exception;
        } finally {
            self::connect($model::class, $cachePath);
        }
    }

    /**
     * Create the file the rebuild writes into.
     *
     * The name is random rather than derived from the pid, and the file is
     * created with O_EXCL (fopen mode 'x'), which fails if anything is already
     * at that path — a symlink included, since O_CREAT|O_EXCL does not follow
     * one. A predictable name plus a following open would let anyone able to
     * write into the cache directory pick a victim file for this process to
     * truncate and then overwrite. Sushi's own fixed path is guessable too, but
     * it is a single name; a pid-derived one is guessable in bulk.
     *
     * Laravel's SQLite connector refuses a database path that does not exist
     * yet, which is why the empty file is created up front.
     */
    private static function createTemporaryFile(string $cachePath): string
    {
        $temporaryPath = $cachePath.'.'.bin2hex(random_bytes(8)).'.building';

        $handle = @fopen($temporaryPath, 'x');

        if ($handle === false) {
            throw new RuntimeException('Could not create a private temporary file at '.$temporaryPath.'.');
        }

        fclose($handle);

        return $temporaryPath;
    }

    /**
     * Sushi::setSqliteConnection() is protected and static, so the call has to
     * happen inside the model's own scope — which is what binding the closure
     * to it does.
     *
     * The class is addressed through the variable rather than through self:: or
     * static::, which both read as this class here and would leave the next
     * reader (or a formatter's self_static_accessor fixer) rewriting one into
     * the other.
     *
     * @param  class-string  $model
     */
    private static function connect(string $model, string $database): void
    {
        $connect = Closure::bind(
            static fn (string $path) => $model::setSqliteConnection($path),
            null,
            $model
        );

        $connect($database);
    }

    /**
     * Ask the trait itself where its cache lives instead of re-deriving the
     * path here — a second derivation is a second thing that can drift.
     */
    private static function readFromSushi(Model $model, string $method): mixed
    {
        return Closure::bind(fn () => $this->{$method}(), $model, $model::class)();
    }

    /**
     * @param  class-string  $class
     */
    private static function report(string $class, Throwable $exception): void
    {
        self::write('Sushi cache could not be prepared for '.$class.'.', [
            'exception' => $exception::class,
            'reason' => self::reportableReason($exception),
        ]);
    }

    /**
     * A condition that repeats on every request gets one line per process, not
     * one per boot: the only Sushi model here sits on the boot path of nearly
     * every page.
     */
    private static function reportOnce(string $key, string $message): void
    {
        if (isset(self::$reported[$key])) {
            return;
        }

        self::$reported[$key] = true;

        self::write($message);
    }

    /**
     * The logger is the one thing that must not turn a handled failure into an
     * unhandled one. An unwritable or misconfigured log sink throws out of
     * Log::warning() itself, and the caller of this class is a model boot with
     * no handler above it — that is #139's failure class through another door.
     *
     * @param  array<string, string>  $context
     */
    private static function write(string $message, array $context = []): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            // There is nothing left to report the failure to report to.
        }
    }

    /**
     * QueryException::formatMessage() substitutes the bindings into the SQL, so
     * a failing insert carries a whole chunk of model rows and the absolute
     * database path in its message. Log context leaves the box verbatim —
     * laravel/nightwatch json_encodes it as-is and its redact_payload_fields
     * does not reach log context — so everything from " (Connection: " onwards
     * is dropped and what remains is bounded. That leaves the SQLSTATE line,
     * which is the part worth reading.
     */
    private static function reportableReason(Throwable $exception): string
    {
        return Str::limit(
            Str::before($exception->getMessage(), ' (Connection: '),
            self::REPORTED_REASON_LIMIT
        );
    }
}
