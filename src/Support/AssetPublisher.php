<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Support;

/**
 * Keeps `public/vendor/statamic-logbook/{css,js}` in step with the
 * bundles shipped inside the package.
 *
 * Statamic only force-publishes addon assets from `statamic:install`
 * ({@see \Statamic\Providers\AddonServiceProvider::bootPublishAfterInstall()}),
 * which plenty of deploys never run — and a `composer update` that
 * ships a newer bundle leaves the old published copy in place. Both
 * land as an unstyled Control Panel, which is the single most reported
 * install problem against this addon. The CP middleware calls this on
 * the way in and copies whatever is missing or stale.
 *
 * Fails soft and silently: a read-only `public/` is a perfectly normal
 * production setup, and `vendor:publish --tag=logbook-assets --force`
 * still covers that case.
 */
final class AssetPublisher
{
    /**
     * Package-relative source => path under `public/`.
     *
     * The published paths are the ones Statamic's `registerStylesheet()`
     * / `registerScript()` use: `vendor/{packageName}/{css,js}/{file}`.
     *
     * @var list<array{string, string}>
     */
    private const ASSETS = [
        ['resources/dist/statamic-logbook.min.css', 'vendor/statamic-logbook/css/statamic-logbook.min.css'],
        ['resources/dist/statamic-logbook.min.js', 'vendor/statamic-logbook/js/statamic-logbook.min.js'],
    ];

    private static bool $ran = false;

    /**
     * @param  bool  $force  Copy regardless of staleness and of the
     *                       `logbook.assets.auto_publish` opt-out —
     *                       used by `logbook:install`.
     */
    public static function ensurePublished(bool $force = false): void
    {
        if (! $force) {
            if (self::$ran) {
                return;
            }

            self::$ran = true;

            if (! (bool) config('logbook.assets.auto_publish', true)) {
                return;
            }
        }

        if (! function_exists('public_path')) {
            return;
        }

        foreach (self::ASSETS as [$source, $target]) {
            self::sync(dirname(__DIR__, 2) . '/' . $source, public_path($target), $force);
        }
    }

    /** Lets the per-process guard be re-armed in tests. */
    public static function reset(): void
    {
        self::$ran = false;
    }

    /**
     * A published copy is stale when it is absent, a different size, or
     * older than the file shipped in the package. Cheap enough to run
     * on a CP request: four stat calls, served from PHP's stat cache.
     */
    public static function isStale(string $source, string $target): bool
    {
        if (! is_file($source)) {
            return false;
        }

        if (! is_file($target)) {
            return true;
        }

        return filesize($target) !== filesize($source)
            || filemtime($target) < filemtime($source);
    }

    private static function sync(string $source, string $target, bool $force): void
    {
        try {
            if (! is_file($source)) {
                return;
            }

            if (! $force && ! self::isStale($source, $target)) {
                return;
            }

            $directory = dirname($target);

            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return;
            }

            @copy($source, $target);
        } catch (\Throwable) {
            // public/ may be read-only; the publish command still works.
        }
    }
}
