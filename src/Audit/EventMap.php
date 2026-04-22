<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Audit;

/**
 * Per-major Statamic event map for audit logging.
 *
 * This class resolves the curated default audit event class list for the
 * Statamic major version running in the host application. It never
 * autoloads event classes by referencing them as `::class`; every entry
 * is stored as a string and probed with {@see class_exists()} before use.
 *
 * That design keeps {@see EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber}
 * safe across Statamic 3, 4, 5, 6 (and future majors) — event classes
 * that are renamed, moved, or removed in a given Statamic major are
 * silently skipped rather than causing a fatal at boot.
 */
final class EventMap
{
    /**
     * Curated high-signal audit events per Statamic major.
     *
     * Keep each list deliberately small. The philosophy is "record
     * meaningful persisted mutations a compliance reviewer would look
     * for" — not every event Statamic happens to fire. Set
     * `logbook.audit_logs.discover_events = true` to opt into broader
     * capture.
     *
     * @var array<int, list<string>>
     */
    private const CURATED = [
        3 => [
            // Entries
            'Statamic\\Events\\EntryDeleted',
            'Statamic\\Events\\EntrySaved',

            // Taxonomy / terms
            'Statamic\\Events\\TaxonomyDeleted',
            'Statamic\\Events\\TaxonomySaved',
            'Statamic\\Events\\TermDeleted',
            'Statamic\\Events\\TermSaved',

            // Global content / navigation
            'Statamic\\Events\\GlobalSetDeleted',
            'Statamic\\Events\\GlobalSetSaved',
            'Statamic\\Events\\NavDeleted',
            'Statamic\\Events\\NavSaved',

            // User/security actions
            'Statamic\\Events\\UserDeleted',
            'Statamic\\Events\\UserSaved',
            'Statamic\\Events\\UserGroupDeleted',
            'Statamic\\Events\\UserGroupSaved',
            'Statamic\\Events\\RoleDeleted',
            'Statamic\\Events\\RoleSaved',
        ],
        4 => [
            'Statamic\\Events\\EntryDeleted',
            'Statamic\\Events\\EntrySaved',
            'Statamic\\Events\\TaxonomyDeleted',
            'Statamic\\Events\\TaxonomySaved',
            'Statamic\\Events\\TermDeleted',
            'Statamic\\Events\\TermSaved',
            'Statamic\\Events\\GlobalSetDeleted',
            'Statamic\\Events\\GlobalSetSaved',
            'Statamic\\Events\\NavDeleted',
            'Statamic\\Events\\NavSaved',
            'Statamic\\Events\\ImpersonationStarted',
            'Statamic\\Events\\ImpersonationEnded',
            'Statamic\\Events\\UserDeleted',
            'Statamic\\Events\\UserSaved',
            'Statamic\\Events\\UserPasswordChanged',
            'Statamic\\Events\\UserGroupDeleted',
            'Statamic\\Events\\UserGroupSaved',
            'Statamic\\Events\\RoleDeleted',
            'Statamic\\Events\\RoleSaved',
        ],
        5 => [
            'Statamic\\Events\\EntryDeleted',
            'Statamic\\Events\\EntrySaved',
            'Statamic\\Events\\TaxonomyDeleted',
            'Statamic\\Events\\TaxonomySaved',
            'Statamic\\Events\\TermDeleted',
            'Statamic\\Events\\TermSaved',
            'Statamic\\Events\\GlobalSetDeleted',
            'Statamic\\Events\\GlobalSetSaved',
            'Statamic\\Events\\NavDeleted',
            'Statamic\\Events\\NavSaved',
            'Statamic\\Events\\ImpersonationStarted',
            'Statamic\\Events\\ImpersonationEnded',
            'Statamic\\Events\\UserDeleted',
            'Statamic\\Events\\UserSaved',
            'Statamic\\Events\\UserPasswordChanged',
            'Statamic\\Events\\UserGroupDeleted',
            'Statamic\\Events\\UserGroupSaved',
            'Statamic\\Events\\RoleDeleted',
            'Statamic\\Events\\RoleSaved',
        ],
        6 => [
            // Entries
            'Statamic\\Events\\EntryDeleted',
            'Statamic\\Events\\EntrySaved',

            // Taxonomy / terms
            'Statamic\\Events\\TaxonomyDeleted',
            'Statamic\\Events\\TaxonomySaved',
            'Statamic\\Events\\TermDeleted',
            'Statamic\\Events\\TermSaved',

            // Global content / navigation
            'Statamic\\Events\\GlobalSetDeleted',
            'Statamic\\Events\\GlobalSetSaved',
            'Statamic\\Events\\NavDeleted',
            'Statamic\\Events\\NavSaved',

            // User/security actions
            'Statamic\\Events\\ImpersonationStarted',
            'Statamic\\Events\\ImpersonationEnded',
            'Statamic\\Events\\UserDeleted',
            'Statamic\\Events\\UserSaved',
            'Statamic\\Events\\UserPasswordChanged',
            'Statamic\\Events\\UserGroupDeleted',
            'Statamic\\Events\\UserGroupSaved',
            'Statamic\\Events\\RoleDeleted',
            'Statamic\\Events\\RoleSaved',

            // Statamic 6-specific security events (introduced with
            // 2FA/passkeys redesign). Included only when the host
            // actually has these classes — class_exists guards
            // them downstream, so older majors ignore them.
            'Statamic\\Events\\TwoFactorAuthenticationEnabled',
            'Statamic\\Events\\TwoFactorAuthenticationDisabled',
        ],
    ];

    /**
     * Curated exclude list per Statamic major.
     *
     * Events in this list are never audited even if they appear in
     * a user-configured allow-list or in discovery mode. These are
     * classes that fire constantly but carry no audit value — blueprints,
     * caching, search reindex, response lifecycle.
     *
     * @var array<int, list<string>>
     */
    private const EXCLUDE = [
        3 => [
            'Statamic\\Events\\EntryCreated',
            'Statamic\\Events\\EntrySaving',
            'Statamic\\Events\\NavTreeSaved',
            'Statamic\\Events\\ResponseCreated',
        ],
        4 => [
            'Statamic\\Events\\EntryCreated',
            'Statamic\\Events\\EntrySaving',
            'Statamic\\Events\\GlobalVariablesDeleted',
            'Statamic\\Events\\GlobalVariablesSaved',
            'Statamic\\Events\\NavTreeSaved',
            'Statamic\\Events\\AssetContainerBlueprintFound',
            'Statamic\\Events\\EntryBlueprintFound',
            'Statamic\\Events\\FormBlueprintFound',
            'Statamic\\Events\\GlobalVariablesBlueprintFound',
            'Statamic\\Events\\TermBlueprintFound',
            'Statamic\\Events\\UserBlueprintFound',
            'Statamic\\Events\\ResponseCreated',
            'Statamic\\Events\\GlideAssetCacheCleared',
            'Statamic\\Events\\GlideCacheCleared',
            'Statamic\\Events\\GlideImageGenerated',
            'Statamic\\Events\\StacheCleared',
            'Statamic\\Events\\StacheWarmed',
            'Statamic\\Events\\StaticCacheCleared',
            'Statamic\\Events\\SearchIndexUpdated',
            'Statamic\\Events\\UrlInvalidated',
        ],
        5 => [
            'Statamic\\Events\\EntryCreated',
            'Statamic\\Events\\EntrySaving',
            'Statamic\\Events\\GlobalVariablesDeleted',
            'Statamic\\Events\\GlobalVariablesSaved',
            'Statamic\\Events\\NavTreeSaved',
            'Statamic\\Events\\AssetContainerBlueprintFound',
            'Statamic\\Events\\EntryBlueprintFound',
            'Statamic\\Events\\FormBlueprintFound',
            'Statamic\\Events\\GlobalVariablesBlueprintFound',
            'Statamic\\Events\\TermBlueprintFound',
            'Statamic\\Events\\UserBlueprintFound',
            'Statamic\\Events\\ResponseCreated',
            'Statamic\\Events\\GlideAssetCacheCleared',
            'Statamic\\Events\\GlideCacheCleared',
            'Statamic\\Events\\GlideImageGenerated',
            'Statamic\\Events\\StacheCleared',
            'Statamic\\Events\\StacheWarmed',
            'Statamic\\Events\\StaticCacheCleared',
            'Statamic\\Events\\SearchIndexUpdated',
            'Statamic\\Events\\UrlInvalidated',
        ],
        6 => [
            'Statamic\\Events\\EntryCreated',
            'Statamic\\Events\\EntrySaving',
            'Statamic\\Events\\GlobalVariablesDeleted',
            'Statamic\\Events\\GlobalVariablesSaved',
            'Statamic\\Events\\NavTreeSaved',
            'Statamic\\Events\\AssetContainerBlueprintFound',
            'Statamic\\Events\\EntryBlueprintFound',
            'Statamic\\Events\\FormBlueprintFound',
            'Statamic\\Events\\GlobalVariablesBlueprintFound',
            'Statamic\\Events\\NavBlueprintFound',
            'Statamic\\Events\\TermBlueprintFound',
            'Statamic\\Events\\UserBlueprintFound',
            'Statamic\\Events\\UserGroupBlueprintFound',
            'Statamic\\Events\\ResponseCreated',
            'Statamic\\Events\\GlideAssetCacheCleared',
            'Statamic\\Events\\GlideCacheCleared',
            'Statamic\\Events\\GlideImageGenerated',
            'Statamic\\Events\\StacheCleared',
            'Statamic\\Events\\StacheWarmed',
            'Statamic\\Events\\StaticCacheCleared',
            'Statamic\\Events\\SearchIndexUpdated',
            'Statamic\\Events\\UrlInvalidated',
            // v6 telemetry/cache plumbing
            'Statamic\\Events\\LicensesRefreshed',
            'Statamic\\Events\\LicenseSet',
        ],
    ];

    /**
     * Fallback major used when the running Statamic version cannot be
     * detected. Kept at the newest supported major so new installs get
     * the richest curated list by default.
     */
    private const FALLBACK_MAJOR = 6;

    /**
     * @var int|null In-process cache of the resolved major.
     */
    private static ?int $cachedMajor = null;

    /**
     * Curated audit event class list for the currently-installed Statamic.
     *
     * Every returned class name is guaranteed to exist at runtime
     * (`class_exists(...)` returned `true`). Callers can listen to the
     * returned list without additional guards.
     *
     * @return list<string>
     */
    public static function curatedEvents(?int $major = null): array
    {
        return self::filterExisting(self::CURATED[self::majorFor($major)] ?? []);
    }

    /**
     * Curated exclude class list for the currently-installed Statamic.
     *
     * Returned class names are NOT filtered by {@see class_exists()}
     * because an exclude-by-string-name is safe even if the class is
     * absent — a string-based set-membership test does not autoload.
     *
     * @return list<string>
     */
    public static function excludedEvents(?int $major = null): array
    {
        return array_values(self::EXCLUDE[self::majorFor($major)] ?? []);
    }

    /**
     * Resolve the Statamic major currently running.
     *
     * Tries {@see \Statamic\Statamic::version()} first (quick),
     * falls back to composer/semver parsing of the installed
     * `statamic/cms` package, and finally to {@see self::FALLBACK_MAJOR}.
     *
     * @param  int|null  $override  Force a specific major. Useful in tests.
     */
    public static function majorFor(?int $override = null): int
    {
        if ($override !== null) {
            return max(1, $override);
        }

        if (self::$cachedMajor !== null) {
            return self::$cachedMajor;
        }

        // Primary: Statamic::version() reports e.g. "6.12.0" in v6.
        if (class_exists(\Statamic\Statamic::class) && method_exists(\Statamic\Statamic::class, 'version')) {
            try {
                $version = (string) \Statamic\Statamic::version();
                if (preg_match('/^(\d+)\./', $version, $m) === 1) {
                    return self::$cachedMajor = (int) $m[1];
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        // Secondary: read composer installed.json so we don't depend on Statamic
        // being booted yet when this is called.
        $installed = self::readInstalledJson();
        if ($installed !== null && preg_match('/^v?(\d+)\./', $installed, $m) === 1) {
            return self::$cachedMajor = (int) $m[1];
        }

        return self::$cachedMajor = self::FALLBACK_MAJOR;
    }

    /**
     * Reset the cached major. Tests only.
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cachedMajor = null;
    }

    /**
     * Filter a list of class names, keeping only those that autoload.
     *
     * @param  list<string>  $classes
     * @return list<string>
     */
    private static function filterExisting(array $classes): array
    {
        $out = [];
        foreach ($classes as $class) {
            if (! is_string($class) || $class === '') {
                continue;
            }
            if (class_exists($class)) {
                $out[] = $class;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Best-effort read of the installed statamic/cms version from
     * vendor/composer/installed.json. Returns null if the file is
     * absent or cannot be parsed.
     */
    private static function readInstalledJson(): ?string
    {
        if (! function_exists('base_path')) {
            return null;
        }

        $path = base_path('vendor/composer/installed.json');
        if (! is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $packages = $decoded['packages'] ?? $decoded ?? [];
        if (! is_array($packages)) {
            return null;
        }

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }
            if (($package['name'] ?? null) !== 'statamic/cms') {
                continue;
            }
            $version = $package['version'] ?? $package['version_normalized'] ?? null;

            return is_string($version) ? $version : null;
        }

        return null;
    }
}
