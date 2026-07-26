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
     * Curated audit events. Every terminal (past-tense) Statamic mutation
     * event that a compliance reviewer would care about.
     *
     * ONE list covers every supported major: entries are stored as strings
     * and filtered through {@see class_exists()} at resolve time, so a class
     * that does not exist in the running major is silently dropped. That is
     * the whole point of the string-based design — there is no need to
     * duplicate near-identical lists per major.
     *
     * `*Created` events are listened to deliberately: they are the only
     * authoritative "this is new" signal. Statamic's own dirty-state
     * (`getOriginal()`) is empty on eloquent-driver-backed objects, so it
     * cannot be trusted to tell a creation from an update. The subscriber
     * suppresses the `*Saved` row that follows a `*Created` for the same
     * subject in the same request, so a creation still yields one row.
     *
     * @var list<string>
     */
    private const CURATED = [
        // --- Entries ---------------------------------------------------
        'Statamic\\Events\\EntryCreated',
        'Statamic\\Events\\EntrySaved',
        'Statamic\\Events\\EntryDeleted',
        'Statamic\\Events\\EntryScheduleReached',

        // --- Collections & their trees (structure changes) -------------
        'Statamic\\Events\\CollectionCreated',
        'Statamic\\Events\\CollectionSaved',
        'Statamic\\Events\\CollectionDeleted',
        'Statamic\\Events\\CollectionTreeSaved',
        'Statamic\\Events\\CollectionTreeDeleted',

        // --- Taxonomies & terms ----------------------------------------
        'Statamic\\Events\\TaxonomyCreated',
        'Statamic\\Events\\TaxonomySaved',
        'Statamic\\Events\\TaxonomyDeleted',
        'Statamic\\Events\\TermCreated',
        'Statamic\\Events\\TermSaved',
        'Statamic\\Events\\TermDeleted',

        // --- Globals ----------------------------------------------------
        // GlobalSet* is the set's config; GlobalVariables* is the actual
        // content operators edit. Both matter.
        'Statamic\\Events\\GlobalSetCreated',
        'Statamic\\Events\\GlobalSetSaved',
        'Statamic\\Events\\GlobalSetDeleted',
        'Statamic\\Events\\GlobalVariablesSaved',

        // --- Navigation --------------------------------------------------
        'Statamic\\Events\\NavCreated',
        'Statamic\\Events\\NavSaved',
        'Statamic\\Events\\NavDeleted',
        'Statamic\\Events\\NavTreeSaved',
        'Statamic\\Events\\NavTreeDeleted',

        // --- Assets -------------------------------------------------------
        'Statamic\\Events\\AssetUploaded',
        'Statamic\\Events\\AssetReuploaded',
        'Statamic\\Events\\AssetReplaced',
        'Statamic\\Events\\AssetSaved',
        'Statamic\\Events\\AssetDeleted',
        'Statamic\\Events\\AssetFolderSaved',
        'Statamic\\Events\\AssetFolderDeleted',
        'Statamic\\Events\\AssetContainerCreated',
        'Statamic\\Events\\AssetContainerSaved',
        'Statamic\\Events\\AssetContainerDeleted',

        // --- Schema: blueprints & fieldsets -------------------------------
        // Field-level schema edits change what every editor sees.
        'Statamic\\Events\\BlueprintCreated',
        'Statamic\\Events\\BlueprintSaved',
        'Statamic\\Events\\BlueprintDeleted',
        'Statamic\\Events\\BlueprintReset',
        'Statamic\\Events\\FieldsetCreated',
        'Statamic\\Events\\FieldsetSaved',
        'Statamic\\Events\\FieldsetDeleted',
        'Statamic\\Events\\FieldsetReset',

        // --- Forms ---------------------------------------------------------
        'Statamic\\Events\\FormCreated',
        'Statamic\\Events\\FormSaved',
        'Statamic\\Events\\FormDeleted',
        'Statamic\\Events\\FormSubmitted',
        'Statamic\\Events\\SubmissionDeleted',

        // --- Sites (v6 multi-site config) -----------------------------------
        'Statamic\\Events\\SiteCreated',
        'Statamic\\Events\\SiteSaved',
        'Statamic\\Events\\SiteDeleted',

        // --- Addon settings ---------------------------------------------------
        'Statamic\\Events\\AddonSettingsSaved',

        // --- Users, roles, groups (security) ------------------------------------
        'Statamic\\Events\\UserCreated',
        'Statamic\\Events\\UserSaved',
        'Statamic\\Events\\UserDeleted',
        'Statamic\\Events\\UserPasswordChanged',
        'Statamic\\Events\\UserGroupSaved',
        'Statamic\\Events\\UserGroupDeleted',
        'Statamic\\Events\\RoleSaved',
        'Statamic\\Events\\RoleDeleted',
        'Statamic\\Events\\ImpersonationStarted',
        'Statamic\\Events\\ImpersonationEnded',
        'Statamic\\Events\\TwoFactorAuthenticationEnabled',
        'Statamic\\Events\\TwoFactorAuthenticationDisabled',
        'Statamic\\Events\\TwoFactorAuthenticationFailed',
        'Statamic\\Events\\TwoFactorRecoveryCodeReplaced',
    ];

    /**
     * Never audited, even if a user allow-lists them or discovery is on.
     *
     * Three groups: (1) events that fire on every render/request and carry
     * no mutation, (2) cache/index/telemetry plumbing, (3) events that
     * duplicate one we already record.
     *
     * @var list<string>
     */
    private const EXCLUDE = [
        // (1) Fires on every render — blueprints are *found*, not changed.
        'Statamic\\Events\\AssetContainerBlueprintFound',
        'Statamic\\Events\\EntryBlueprintFound',
        'Statamic\\Events\\FormBlueprintFound',
        'Statamic\\Events\\GlobalVariablesBlueprintFound',
        'Statamic\\Events\\NavBlueprintFound',
        'Statamic\\Events\\TermBlueprintFound',
        'Statamic\\Events\\UserBlueprintFound',
        'Statamic\\Events\\UserGroupBlueprintFound',
        'Statamic\\Events\\ResponseCreated',

        // (2) Cache, search, image and licence plumbing.
        'Statamic\\Events\\GlideAssetCacheCleared',
        'Statamic\\Events\\GlideCacheCleared',
        'Statamic\\Events\\GlideImageGenerated',
        'Statamic\\Events\\StacheCleared',
        'Statamic\\Events\\StacheWarmed',
        'Statamic\\Events\\StaticCacheCleared',
        'Statamic\\Events\\SearchIndexUpdated',
        'Statamic\\Events\\UrlInvalidated',
        'Statamic\\Events\\LicensesRefreshed',
        'Statamic\\Events\\LicenseSet',
        'Statamic\\Events\\DefaultPreferencesSaved',

        // (3) Duplicates / bookkeeping side-effects of an event we record.
        'Statamic\\Events\\DuplicateIdRegenerated',
        'Statamic\\Events\\AssetReferencesUpdated',
        'Statamic\\Events\\TermReferencesUpdated',
        'Statamic\\Events\\CollectionTreeEntriesMovedOrRemoved',
        // Fires LAST in Asset::upload() (after AssetSaved + AssetUploaded),
        // so it cannot serve as the creation signal there; the upload row
        // already covers the act.
        'Statamic\\Events\\AssetCreated',
        'Statamic\\Events\\LocalizedTermSaved',
        'Statamic\\Events\\LocalizedTermDeleted',
        'Statamic\\Events\\SubmissionCreated',
        'Statamic\\Events\\SubmissionSaved',
        // Fire alongside GlobalSetCreated/GlobalSetDeleted for one act.
        'Statamic\\Events\\GlobalVariablesCreated',
        'Statamic\\Events\\GlobalVariablesDeleted',
        // Fires right after the UserCreated+UserSaved pair on front-end
        // registration — same act, third event.
        'Statamic\\Events\\UserRegistered',
        // Revisions fire alongside every entry save when revisions are on.
        'Statamic\\Events\\RevisionSaved',
        'Statamic\\Events\\RevisionDeleted',
        // Fires on every 2FA-protected page load, not a state change.
        'Statamic\\Events\\TwoFactorAuthenticationChallenged',
        'Statamic\\Events\\ValidTwoFactorAuthenticationCodeProvided',
    ];

    /**
     * Partition of CURATED into the operator-facing groups that the CP
     * settings page toggles. Every curated event MUST appear in exactly
     * one group (EventMapTest asserts the partition is exact) — a curated
     * event outside any group could never be switched off from the UI.
     *
     * @var array<string, list<string>>
     */
    private const GROUPS = [
        'content' => [
            'Statamic\\Events\\EntryCreated',
            'Statamic\\Events\\EntrySaved',
            'Statamic\\Events\\EntryDeleted',
            'Statamic\\Events\\EntryScheduleReached',
            'Statamic\\Events\\CollectionCreated',
            'Statamic\\Events\\CollectionSaved',
            'Statamic\\Events\\CollectionDeleted',
            'Statamic\\Events\\CollectionTreeSaved',
            'Statamic\\Events\\CollectionTreeDeleted',
            'Statamic\\Events\\TaxonomyCreated',
            'Statamic\\Events\\TaxonomySaved',
            'Statamic\\Events\\TaxonomyDeleted',
            'Statamic\\Events\\TermCreated',
            'Statamic\\Events\\TermSaved',
            'Statamic\\Events\\TermDeleted',
            'Statamic\\Events\\GlobalSetCreated',
            'Statamic\\Events\\GlobalSetSaved',
            'Statamic\\Events\\GlobalSetDeleted',
            'Statamic\\Events\\GlobalVariablesSaved',
            'Statamic\\Events\\NavCreated',
            'Statamic\\Events\\NavSaved',
            'Statamic\\Events\\NavDeleted',
            'Statamic\\Events\\NavTreeSaved',
            'Statamic\\Events\\NavTreeDeleted',
        ],
        'assets' => [
            'Statamic\\Events\\AssetUploaded',
            'Statamic\\Events\\AssetReuploaded',
            'Statamic\\Events\\AssetReplaced',
            'Statamic\\Events\\AssetSaved',
            'Statamic\\Events\\AssetDeleted',
            'Statamic\\Events\\AssetFolderSaved',
            'Statamic\\Events\\AssetFolderDeleted',
            'Statamic\\Events\\AssetContainerCreated',
            'Statamic\\Events\\AssetContainerSaved',
            'Statamic\\Events\\AssetContainerDeleted',
        ],
        'schema' => [
            'Statamic\\Events\\BlueprintCreated',
            'Statamic\\Events\\BlueprintSaved',
            'Statamic\\Events\\BlueprintDeleted',
            'Statamic\\Events\\BlueprintReset',
            'Statamic\\Events\\FieldsetCreated',
            'Statamic\\Events\\FieldsetSaved',
            'Statamic\\Events\\FieldsetDeleted',
            'Statamic\\Events\\FieldsetReset',
        ],
        'forms' => [
            'Statamic\\Events\\FormCreated',
            'Statamic\\Events\\FormSaved',
            'Statamic\\Events\\FormDeleted',
            'Statamic\\Events\\FormSubmitted',
            'Statamic\\Events\\SubmissionDeleted',
        ],
        'users' => [
            'Statamic\\Events\\UserCreated',
            'Statamic\\Events\\UserSaved',
            'Statamic\\Events\\UserDeleted',
            'Statamic\\Events\\UserGroupSaved',
            'Statamic\\Events\\UserGroupDeleted',
            'Statamic\\Events\\RoleSaved',
            'Statamic\\Events\\RoleDeleted',
        ],
        'security' => [
            'Statamic\\Events\\UserPasswordChanged',
            'Statamic\\Events\\ImpersonationStarted',
            'Statamic\\Events\\ImpersonationEnded',
            'Statamic\\Events\\TwoFactorAuthenticationEnabled',
            'Statamic\\Events\\TwoFactorAuthenticationDisabled',
            'Statamic\\Events\\TwoFactorAuthenticationFailed',
            'Statamic\\Events\\TwoFactorRecoveryCodeReplaced',
        ],
        'system' => [
            'Statamic\\Events\\SiteCreated',
            'Statamic\\Events\\SiteSaved',
            'Statamic\\Events\\SiteDeleted',
            'Statamic\\Events\\AddonSettingsSaved',
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
        return self::filterExisting(self::CURATED);
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
        return array_values(self::EXCLUDE);
    }

    /**
     * Event classes belonging to the given operator-facing group keys.
     * Used by SettingsRepository to translate "group switched off in the
     * CP" into exclude-list entries. Returns strings, not class_exists-
     * filtered — string exclusion is safe for absent classes.
     *
     * @param  list<string>  $groupKeys
     * @return list<string>
     */
    public static function eventsForGroups(array $groupKeys): array
    {
        $out = [];
        foreach ($groupKeys as $key) {
            foreach (self::GROUPS[$key] ?? [] as $class) {
                $out[] = $class;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The full group partition, for tests and UI introspection.
     *
     * @return array<string, list<string>>
     */
    public static function groupPartition(): array
    {
        return self::GROUPS;
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

        // base_path() exists as a function but still throws when the bound
        // container is not a full Application (bare container, early boot).
        try {
            $path = base_path('vendor/composer/installed.json');
        } catch (\Throwable) {
            return null;
        }

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
