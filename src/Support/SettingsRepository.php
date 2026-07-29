<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Support;

use EmranAlhaddad\StatamicLogbook\Audit\EventMap;
use Illuminate\Support\Facades\DB;

/**
 * CP-managed addon settings, stored in the logbook database so operators
 * configure everything from /cp/utilities/logbook/settings instead of env.
 *
 * Shape (stored as one JSON row, key = 'settings'):
 *
 *   configured        bool   onboarding (DB setup + install) completed
 *   system_logs       bool   master switch for the system log stream
 *   system_levels     array<level, bool>  which log levels are captured
 *   audit_logs        bool   master switch for the audit stream
 *   disabled_events   list<class-string>  audit events switched off
 *   activity_views    bool   CP page-view tracking
 *   retention_days    int
 *   ignore_fields_extra  string  comma-separated extra diff-ignored fields
 *   ignore_channels_extra string comma-separated extra ignored log channels
 *
 * File/env config remains the source of DEFAULTS; anything saved here
 * overrides it at boot via applyToConfig(). Every read path is
 * failure-proof by contract: no DB, missing table, malformed JSON — all
 * yield defaults and never throw.
 */
class SettingsRepository
{
    public const TABLE = 'logbook_settings';

    public const KEY = 'settings';

    /** @var list<string> PSR-3 levels, most to least severe */
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /**
     * CP surfaces the page-view tracker can record, key => label.
     * Keys are shared with LogCpPageViews::ROUTES (test-enforced).
     *
     * @var array<string, string>
     */
    public const VIEW_PAGES = [
        'dashboard' => 'Dashboard',
        'entries' => 'Entry editor',
        'collections' => 'Collection listings',
        'taxonomies' => 'Taxonomies & terms',
        'navigation' => 'Navigation editor',
        'globals' => 'Globals editor',
        'assets' => 'Asset browser',
        'users' => 'User profiles, groups & roles',
        'user_listing' => 'User listing',
        'forms' => 'Forms & submissions',
        'blueprints' => 'Blueprints & fieldsets',
    ];

    /** Special sentinel in view_excluded_roles meaning "super admins". */
    public const EXCLUDE_SUPERS = '__super';

    /** @var array<string, mixed>|null in-process memo */
    private static ?array $cache = null;

    /**
     * Group metadata for the audit section of the settings UI. Keys match
     * EventMap::groupPartition() exactly (test-enforced).
     *
     * @return array<string, string> group key => label
     */
    public static function groupLabels(): array
    {
        return [
            'entries' => 'Entries',
            'collections' => 'Collections',
            'taxonomies' => 'Taxonomies & Terms',
            'navigation' => 'Navigation',
            'globals' => 'Globals',
            'assets' => 'Assets',
            'blueprints' => 'Blueprints & Fieldsets',
            'forms' => 'Forms',
            'users' => 'Users & Access',
            'sites' => 'Sites & System',
        ];
    }

    /**
     * Defaults: everything on except page views.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'configured' => false,
            'system_logs' => true,
            'system_levels' => array_fill_keys(self::LEVELS, true),
            'audit_logs' => true,
            'disabled_events' => [],
            'activity_views' => false,
            'view_pages' => array_fill_keys(array_keys(self::VIEW_PAGES), true),
            'view_dedupe_minutes' => 5,
            'view_excluded_roles' => [],
            'view_retention_days' => 30,
            'retention_days' => 365,
            'ignore_fields_extra' => '',
            'ignore_channels_extra' => '',
        ];
    }

    /**
     * Current effective settings: defaults overlaid with whatever the
     * operator saved. Never throws.
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];

        try {
            $conn = DbConnectionResolver::resolve();
            $row = DB::connection($conn)->table(self::TABLE)->where('key', self::KEY)->first();

            if ($row !== null && is_string($row->value ?? null)) {
                $decoded = json_decode($row->value, true);
                if (is_array($decoded)) {
                    $stored = $decoded;
                }
            }
        } catch (\Throwable) {
            // No DB / no table / bad credentials — run on defaults.
        }

        return self::$cache = self::sanitize($stored);
    }

    /**
     * Whether onboarding (DB setup + install) has been completed. False
     * also when the DB is unreachable — the setup screen doubles as the
     * "fix your DB" screen, which is exactly where an operator should land.
     */
    public static function isConfigured(): bool
    {
        return (bool) (self::current()['configured'] ?? false);
    }

    /**
     * Whether the settings table exists (distinguishes "not onboarded"
     * from "not installed"). Never throws.
     */
    public static function isInstalled(): bool
    {
        try {
            $conn = DbConnectionResolver::resolve();

            return DB::connection($conn)->getSchemaBuilder()->hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Effective stream switches for tab/route gating. Uses saved settings
     * when configured, file config otherwise.
     *
     * @return array{system: bool, audit: bool}
     */
    public static function streams(): array
    {
        $s = self::current();

        if ($s['configured']) {
            return ['system' => (bool) $s['system_logs'], 'audit' => (bool) $s['audit_logs']];
        }

        return [
            'system' => (bool) config('logbook.system_logs.enabled', true),
            'audit' => (bool) config('logbook.audit_logs.enabled', true),
        ];
    }

    /**
     * Persist a settings payload (sanitized). Returns false on failure
     * instead of throwing.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function save(array $settings): bool
    {
        $clean = self::sanitize($settings);

        try {
            $conn = DbConnectionResolver::resolve();

            DB::connection($conn)->table(self::TABLE)->updateOrInsert(
                ['key' => self::KEY],
                ['value' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
            );

            self::$cache = $clean;

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Overlay the stored settings onto the logbook config. Called from the
     * provider BEFORE the audit subscriber / log handler read config, so
     * the CP-managed values win over env without touching .env.
     */
    public static function applyToConfig(): void
    {
        $s = self::current();

        // Nothing was ever saved — env/file config stays authoritative.
        if (! ($s['configured'] ?? false)) {
            return;
        }

        config([
            'logbook.system_logs.enabled' => (bool) $s['system_logs'],
            'logbook.audit_logs.enabled' => (bool) $s['audit_logs'],
            'logbook.activity.enabled' => (bool) $s['activity_views'],
            'logbook.activity.pages' => array_keys(array_filter($s['view_pages'])),
            'logbook.activity.dedupe_minutes' => (int) $s['view_dedupe_minutes'],
            'logbook.activity.excluded_roles' => $s['view_excluded_roles'],
            'logbook.activity.retention_days' => (int) $s['view_retention_days'],
            'logbook.retention_days' => (int) $s['retention_days'],
            // Which log levels the system stream keeps.
            'logbook.system_logs.capture_levels' => array_keys(array_filter($s['system_levels'])),
        ]);

        // Disabled audit events become exclude-list entries — the
        // subscriber already honours excludes (for Statamic AND the
        // Laravel auth classes), so no new mechanism is needed.
        if ($s['disabled_events'] !== []) {
            config([
                'logbook.audit_logs.exclude_events' => array_values(array_unique(array_merge(
                    (array) config('logbook.audit_logs.exclude_events', []),
                    $s['disabled_events']
                ))),
            ]);
        }

        $extraFields = self::csv($s['ignore_fields_extra']);
        if ($extraFields !== []) {
            config([
                'logbook.audit_logs.ignore_fields' => array_values(array_unique(array_merge(
                    (array) config('logbook.audit_logs.ignore_fields', []),
                    $extraFields
                ))),
            ]);
        }

        $extraChannels = self::csv($s['ignore_channels_extra']);
        if ($extraChannels !== []) {
            config([
                'logbook.system_logs.ignore_channels' => array_values(array_unique(array_merge(
                    (array) config('logbook.system_logs.ignore_channels', []),
                    $extraChannels
                ))),
            ]);
        }
    }

    /**
     * Create the settings table if it is missing. Single definition, shared
     * by {@see \EmranAlhaddad\StatamicLogbook\Console\InstallCommand} and by
     * upgrade adoption. Never throws — returns whether the table is usable.
     */
    public static function ensureTable(): bool
    {
        try {
            $conn = DbConnectionResolver::resolve();
            $schema = DB::connection($conn)->getSchemaBuilder();

            if ($schema->hasTable(self::TABLE)) {
                return true;
            }

            $schema->create(self::TABLE, function ($table): void {
                $table->string('key', 64)->primary();
                $table->json('value')->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Does this install predate the CP settings table? Detected from the log
     * tables, not {@see isInstalled()} — that checks the settings table,
     * which by definition does not exist on a pre-2.1 install.
     */
    public static function hasLogTables(): bool
    {
        try {
            $schema = DB::connection(DbConnectionResolver::resolve())->getSchemaBuilder();

            return $schema->hasTable('logbook_audit_logs') || $schema->hasTable('logbook_system_logs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Has an operator (or the upgrade adoption) ever written settings?
     */
    public static function hasStoredSettings(): bool
    {
        try {
            $conn = DbConnectionResolver::resolve();

            return DB::connection($conn)->table(self::TABLE)->where('key', self::KEY)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Translate the .env / config/logbook.php configuration of a pre-2.1
     * install into the CP settings shape, so upgrading does not silently
     * reset an operator's choices.
     *
     * Only values that {@see applyToConfig()} would OVERRIDE need importing.
     * The ignore-channel / ignore-field lists are merged additively on top of
     * file config, so env-provided entries keep working and the "extra" boxes
     * stay empty rather than duplicating them.
     *
     * @return array<string, mixed>
     */
    public static function importFromFileConfig(): array
    {
        $s = self::defaults();

        $s['configured'] = true;
        $s['system_logs'] = (bool) config('logbook.system_logs.enabled', true);
        $s['audit_logs'] = (bool) config('logbook.audit_logs.enabled', true);
        $s['retention_days'] = (int) config('logbook.retention_days', $s['retention_days']);

        // Page-view tracking is new and high-volume: never switch it on for
        // someone who is merely upgrading.
        $s['activity_views'] = false;

        $s['system_levels'] = self::levelsFromThreshold(
            (array) config('logbook.system_logs.capture_levels', []),
            (string) config('logbook.system_logs.level', 'debug')
        );

        // Env-excluded events are shown as unchecked, so the settings page
        // reflects what is actually being captured instead of claiming
        // everything is on.
        $known = self::knownEventClasses();
        $s['disabled_events'] = array_values(array_intersect(
            array_values(array_filter((array) config('logbook.audit_logs.exclude_events', []), 'is_string')),
            $known
        ));

        return $s;
    }

    /**
     * Per-level switches for an install that only had a minimum-severity
     * threshold. `capture_levels` (2.1+) wins when present; otherwise every
     * level at or above the old threshold is enabled — enabling all of them
     * would multiply an upgrader's log volume.
     *
     * @param  list<string>  $captureLevels
     * @return array<string, bool>
     */
    private static function levelsFromThreshold(array $captureLevels, string $threshold): array
    {
        $captureLevels = array_values(array_filter($captureLevels, 'is_string'));

        if ($captureLevels !== []) {
            $on = array_map('strtolower', $captureLevels);

            return array_combine(
                self::LEVELS,
                array_map(fn (string $l): bool => in_array($l, $on, true), self::LEVELS)
            );
        }

        // self::LEVELS is ordered most → least severe.
        $cut = array_search(strtolower($threshold), self::LEVELS, true);
        if ($cut === false) {
            $cut = count(self::LEVELS) - 1; // unknown threshold → keep everything
        }

        $out = [];
        foreach (self::LEVELS as $i => $level) {
            $out[$level] = $i <= $cut;
        }

        return $out;
    }

    /**
     * Adopt a pre-2.1 install: seed the settings row from env/file config and
     * mark it configured, so the operator keeps their configuration and is
     * not sent through first-run database setup.
     *
     * Idempotent and safe to call on every request — a no-op once settings
     * exist, or when there is nothing to adopt.
     */
    public static function adoptExistingInstall(): bool
    {
        if (! self::hasLogTables() || self::hasStoredSettings()) {
            return false;
        }

        // A pre-2.1 install has no settings table yet, so the write below
        // would fail silently and the operator would be shown first-run
        // database setup instead.
        if (! self::ensureTable()) {
            return false;
        }

        $ok = self::save(self::importFromFileConfig());

        if ($ok) {
            self::resetCache();
        }

        return $ok;
    }

    /**
     * Reset the in-process memo. Tests + post-save re-reads.
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /**
     * Coerce arbitrary input (stored JSON or a form POST) into the exact
     * settings shape. Unknown keys dropped, wrong types coerced. Unknown
     * event classes in disabled_events are kept as-is (harmless strings;
     * the exclude mechanism ignores classes that don't exist).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();

        $levels = [];
        foreach (self::LEVELS as $level) {
            $levels[$level] = self::bool($input['system_levels'][$level] ?? true);
        }

        $known = self::knownEventClasses();
        $disabled = array_values(array_unique(array_filter(
            (array) ($input['disabled_events'] ?? []),
            static fn ($e): bool => is_string($e) && in_array($e, $known, true)
        )));

        $retention = self::intIn($input['retention_days'] ?? $d['retention_days'], 1, 3650, $d['retention_days']);
        $viewRetention = self::intIn($input['view_retention_days'] ?? $d['view_retention_days'], 1, 3650, $d['view_retention_days']);
        $dedupe = self::intIn($input['view_dedupe_minutes'] ?? $d['view_dedupe_minutes'], 0, 1440, $d['view_dedupe_minutes']);

        $pages = [];
        foreach (array_keys(self::VIEW_PAGES) as $key) {
            $pages[$key] = self::bool($input['view_pages'][$key] ?? true);
        }

        // Role handles are free-form strings (host-defined); cap length and
        // count, keep the supers sentinel.
        $roles = array_values(array_unique(array_filter(array_map(
            static fn ($r) => is_string($r) ? mb_substr(trim($r), 0, 64) : '',
            (array) ($input['view_excluded_roles'] ?? [])
        ))));
        $roles = array_slice($roles, 0, 50);

        return [
            'configured' => self::bool($input['configured'] ?? $d['configured']),
            'system_logs' => self::bool($input['system_logs'] ?? $d['system_logs']),
            'system_levels' => $levels,
            'audit_logs' => self::bool($input['audit_logs'] ?? $d['audit_logs']),
            'disabled_events' => $disabled,
            'activity_views' => self::bool($input['activity_views'] ?? $d['activity_views']),
            'view_pages' => $pages,
            'view_dedupe_minutes' => $dedupe,
            'view_excluded_roles' => $roles,
            'view_retention_days' => $viewRetention,
            'retention_days' => $retention,
            'ignore_fields_extra' => mb_substr(trim((string) ($input['ignore_fields_extra'] ?? '')), 0, 1000),
            'ignore_channels_extra' => mb_substr(trim((string) ($input['ignore_channels_extra'] ?? '')), 0, 1000),
        ];
    }

    private static function intIn(mixed $v, int $min, int $max, int $fallback): int
    {
        $n = (int) $v;

        return ($n < $min || $n > $max) ? $fallback : $n;
    }

    /**
     * Every event class the settings page can toggle, across all groups.
     *
     * @return list<string>
     */
    private static function knownEventClasses(): array
    {
        return array_merge(...array_values(EventMap::groupPartition()));
    }

    /** @return list<string> */
    private static function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private static function bool(mixed $v): bool
    {
        // Accepts real booleans, "1"/"0", "on" (HTML checkboxes), "true"/"false".
        return filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
