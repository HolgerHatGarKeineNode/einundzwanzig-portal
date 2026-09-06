<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
     * The rebuild did not happen. Sushi's own lazy path takes over, which is
     * exactly today's behaviour — never worse than before this class existed.
     */
    public const FAILED = 'failed';

    /**
     * @var array<class-string, bool>
     */
    private static array $usesSushi = [];

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
     * Never throws: the caller is a model boot, and a failure here has to leave
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
            $dataPath = self::readFromSushi($model, 'sushiCacheReferencePath');

            if (self::isComplete($model, $cachePath, $dataPath)) {
                return self::FRESH;
            }

            return self::rebuildExclusively($model, $cachePath, $dataPath);
        } catch (Throwable $exception) {
            Log::warning('Sushi cache could not be prepared for '.$class.'.', [
                'exception' => $exception->getMessage(),
            ]);

            return self::FAILED;
        }
    }

    /**
     * Every Sushi-backed model of this application and of the packages that
     * declare a dependency on calebporzio/sushi.
     *
     * Discovery, rather than a hardcoded list, so that a second Sushi model
     * added by a package upgrade is warmed without anyone remembering to add
     * it. A file only qualifies when it declares a class of its own name and
     * mentions Sushi — that keeps this away from files whose name is not a
     * class (app/helpers.php would be re-included and redeclare its
     * functions). A model that inherits the trait from a parent without naming
     * Sushi anywhere in its own file is therefore not discovered here; it is
     * still healed at runtime, where the guard hooks the model boot itself.
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
    private static function isComplete(Model $model, string $cachePath, string $dataPath): bool
    {
        if (! is_file($cachePath) || ! is_file($dataPath)) {
            return false;
        }

        if (filemtime($cachePath) < filemtime($dataPath) || filesize($cachePath) === 0) {
            return false;
        }

        return self::countRowsIn($cachePath, $model->getTable()) === count($model->getRows());
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
     * pid-suffixed file and publishes it whole — but without it, every request
     * that arrives during a rebuild would redundantly rebuild 249 rows of its
     * own, which is precisely the burst the deploy window produces.
     */
    private static function rebuildExclusively(Model $model, string $cachePath, string $dataPath): string
    {
        $lock = @fopen($cachePath.'.lock', 'c');

        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }

        try {
            // Another process may have finished the rebuild while this one waited.
            if (self::isComplete($model, $cachePath, $dataPath)) {
                return self::FRESH;
            }

            self::rebuild($model, $cachePath, $dataPath);

            return self::REBUILT;
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
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
     * where this guard runs.
     */
    private static function rebuild(Model $model, string $cachePath, string $dataPath): void
    {
        $temporaryPath = $cachePath.'.'.getmypid().'.building';

        // Laravel's SQLite connector refuses a database path that does not
        // exist yet, so the empty file has to be there before connecting —
        // which is harmless here, because nothing reads this path.
        file_put_contents($temporaryPath, '');

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

            touch($temporaryPath, filemtime($dataPath));

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
}
