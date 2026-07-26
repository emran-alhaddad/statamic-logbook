<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Support;

use EmranAlhaddad\StatamicLogbook\Audit\EventMap;
use Illuminate\Support\Facades\DB;

/**
 * CP-managed addon settings, stored in the logbook database so operators
 * configure everything from /cp/utilities/logbook/settings instead of env.
 *
 * Storage: single row (key = 'settings') in `logbook_settings` holding a
 * JSON blob. File/env config remains the source of DEFAULTS; anything the
 * operator saves here overrides it at boot via applyToConfig().
 *
 * Every read path is failure-proof by contract: no DB, missing table,
 * malformed JSON — all yield defaults and never throw. This runs during
 * provider boot on every request, so a broken logbook DB must never take
 * the host site down.
 */
class SettingsRepository
{
    public const TABLE = 'logbook_settings';

    public const KEY = 'settings';

    /** @var array<string, mixed>|null in-process memo */
    private static ?array $cache = null;

    /**
     * Event groups shown as toggles in the CP. Keys are stored in settings;
     * labels/descriptions render in the settings + onboarding UIs.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function groups(): array
    {
        return [
            'content' => [
                'label' => 'Content',
                'description' => 'Entries, collections, structures, taxonomies, terms, globals, navigations.',
            ],
            'assets' => [
                'label' => 'Assets',
                'description' => 'Uploads, edits, deletions, folders and containers.',
            ],
            'schema' => [
                'label' => 'Blueprints & Fieldsets',
                'description' => 'Field schema changes that affect every editor.',
            ],
            'forms' => [
                'label' => 'Forms',
                'description' => 'Form configuration and front-end submissions.',
            ],
            'users' => [
                'label' => 'Users, Roles & Groups',
                'description' => 'Account lifecycle and permission changes.',
            ],
            'security' => [
                'label' => 'Security',
                'description' => 'Two-factor changes, impersonation, password changes.',
            ],
            'system' => [
                'label' => 'Sites & System',
                'description' => 'Site configuration and addon settings.',
            ],
        ];
    }

    /**
     * Defaults for a fresh, unconfigured install. Presets in onboarding
     * are expressed as deltas over this.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'configured' => false,
            'system_logs' => true,
            'audit_logs' => true,
            'auth_events' => true,
            'activity_views' => false,
            'groups' => array_fill_keys(array_keys(self::groups()), true),
            'retention_days' => 365,
            'ignore_fields_extra' => '',
        ];
    }

    /**
     * Onboarding presets. Each is a complete settings payload.
     *
     * @return array<string, array{label: string, description: string, settings: array<string, mixed>}>
     */
    public static function presets(): array
    {
        $base = self::defaults();
        $base['configured'] = true;

        $minimal = $base;
        $minimal['groups'] = array_fill_keys(array_keys(self::groups()), false);
        $minimal['groups']['content'] = true;
        $minimal['groups']['users'] = true;
        $minimal['groups']['security'] = true;

        $everything = $base;
        $everything['activity_views'] = true;

        return [
            'minimal' => [
                'label' => 'Minimal',
                'description' => 'Content, users and security only. Quietest option.',
                'settings' => $minimal,
            ],
            'recommended' => [
                'label' => 'Recommended',
                'description' => 'Every change in the CMS, without page-view tracking.',
                'settings' => $base,
            ],
            'everything' => [
                'label' => 'Everything',
                'description' => 'All changes plus page-view activity (who opened what).',
                'settings' => $everything,
            ],
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
     * Whether onboarding has been completed. False also when the DB is
     * unreachable — the onboarding screen doubles as the "fix your DB"
     * screen, so that is exactly where an operator should land.
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
            'logbook.audit_logs.auth_events' => (bool) $s['auth_events'],
            'logbook.activity.enabled' => (bool) $s['activity_views'],
            'logbook.retention_days' => (int) $s['retention_days'],
        ]);

        // Disabled groups become exclude-list entries — the subscriber
        // already honours excludes, so no new mechanism is needed.
        $disabled = array_keys(array_filter($s['groups'], static fn ($on) => ! $on));
        if ($disabled !== []) {
            config([
                'logbook.audit_logs.exclude_events' => array_values(array_unique(array_merge(
                    (array) config('logbook.audit_logs.exclude_events', []),
                    EventMap::eventsForGroups($disabled)
                ))),
            ]);
        }

        $extra = array_values(array_filter(array_map('trim', explode(',', (string) $s['ignore_fields_extra']))));
        if ($extra !== []) {
            config([
                'logbook.audit_logs.ignore_fields' => array_values(array_unique(array_merge(
                    (array) config('logbook.audit_logs.ignore_fields', []),
                    $extra
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
     * settings shape. Unknown keys dropped, wrong types coerced.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $d = self::defaults();

        $groups = [];
        foreach (array_keys(self::groups()) as $g) {
            $groups[$g] = self::bool($input['groups'][$g] ?? $d['groups'][$g]);
        }

        $retention = (int) ($input['retention_days'] ?? $d['retention_days']);
        if ($retention < 1 || $retention > 3650) {
            $retention = $d['retention_days'];
        }

        return [
            'configured' => self::bool($input['configured'] ?? $d['configured']),
            'system_logs' => self::bool($input['system_logs'] ?? $d['system_logs']),
            'audit_logs' => self::bool($input['audit_logs'] ?? $d['audit_logs']),
            'auth_events' => self::bool($input['auth_events'] ?? $d['auth_events']),
            'activity_views' => self::bool($input['activity_views'] ?? $d['activity_views']),
            'groups' => $groups,
            'retention_days' => $retention,
            'ignore_fields_extra' => mb_substr(trim((string) ($input['ignore_fields_extra'] ?? '')), 0, 1000),
        ];
    }

    private static function bool(mixed $v): bool
    {
        // Accepts real booleans, "1"/"0", "on" (HTML checkboxes), "true"/"false".
        return filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
