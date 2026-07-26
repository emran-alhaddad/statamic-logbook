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

        $known = array_merge(...array_values(EventMap::groupPartition()));
        $disabled = array_values(array_unique(array_filter(
            (array) ($input['disabled_events'] ?? []),
            static fn ($e): bool => is_string($e) && in_array($e, $known, true)
        )));

        $retention = (int) ($input['retention_days'] ?? $d['retention_days']);
        if ($retention < 1 || $retention > 3650) {
            $retention = $d['retention_days'];
        }

        return [
            'configured' => self::bool($input['configured'] ?? $d['configured']),
            'system_logs' => self::bool($input['system_logs'] ?? $d['system_logs']),
            'system_levels' => $levels,
            'audit_logs' => self::bool($input['audit_logs'] ?? $d['audit_logs']),
            'disabled_events' => $disabled,
            'activity_views' => self::bool($input['activity_views'] ?? $d['activity_views']),
            'retention_days' => $retention,
            'ignore_fields_extra' => mb_substr(trim((string) ($input['ignore_fields_extra'] ?? '')), 0, 1000),
            'ignore_channels_extra' => mb_substr(trim((string) ($input['ignore_channels_extra'] ?? '')), 0, 1000),
        ];
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
