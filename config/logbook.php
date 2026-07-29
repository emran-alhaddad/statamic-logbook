<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Statamic Logbook configuration
|--------------------------------------------------------------------------
|
| Every option here has an env-var override. Event classes are stored as
| fully-qualified class-name STRINGS (never `::class` expressions) so that
| this file can be safely loaded under Statamic majors where a given event
| class does not exist. The audit subscriber filters the list through
| `class_exists()` at runtime.
|
| If you are configuring `audit_logs.events` or `audit_logs.exclude_events`
| yourself, write fully-qualified class names as strings:
|
|     'audit_logs' => [
|         'events' => [
|             'Statamic\\Events\\EntrySaved',
|         ],
|     ],
|
| Using `\Statamic\Events\EntrySaved::class` also works (it produces a
| string at parse time and does not autoload the class), but string form
| is preferred for forward compatibility.
*/

return [

    'db' => [
        'connection' => [
            'driver' => env('LOGBOOK_DB_CONNECTION'),
            'host' => env('LOGBOOK_DB_HOST'),
            'port' => env('LOGBOOK_DB_PORT', '3306'),
            'database' => env('LOGBOOK_DB_DATABASE'),
            'username' => env('LOGBOOK_DB_USERNAME'),
            'password' => env('LOGBOOK_DB_PASSWORD'),
            'unix_socket' => env('LOGBOOK_DB_SOCKET', ''),
            'charset' => env('LOGBOOK_DB_CHARSET', 'utf8mb4'),
            'collation' => env('LOGBOOK_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],

    'system_logs' => [
        'enabled' => (bool) env('LOGBOOK_SYSTEM_LOGS_ENABLED', true),
        'level' => env('LOGBOOK_SYSTEM_LOGS_LEVEL', 'debug'),
        'bubble' => (bool) env('LOGBOOK_SYSTEM_LOGS_BUBBLE', true),
        // Ignore noisy framework channels/messages by default.
        'ignore_channels' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS',
            'deprecations'
        ))))),
        'ignore_message_contains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES',
            'Since symfony/http-foundation,Unable to create configured logger. Using emergency logger.'
        ))))),
    ],

    'audit_logs' => [

        // Audit capture master switch. Set false to disable entirely.
        'enabled' => (bool) env('LOGBOOK_AUDIT_LOGS_ENABLED', true),

        // When true, discover additional Statamic events by scanning the
        // installed statamic/cms package. Most installations should keep
        // this false and rely on the curated per-major defaults from
        // EmranAlhaddad\StatamicLogbook\Audit\EventMap.
        'discover_events' => (bool) env('LOGBOOK_AUDIT_DISCOVER_EVENTS', false),

        // When true (default), merge EventMap's curated per-major list
        // with whatever is configured below. Set false to use ONLY the
        // classes in `events` below, ignoring the curated defaults.
        'use_curated_defaults' => (bool) env('LOGBOOK_AUDIT_USE_CURATED_DEFAULTS', true),

        // Additional event class names to listen for on top of the
        // curated per-major defaults. Write as string FQCNs for cross-
        // version safety.
        //
        // @var list<string>
        'events' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'LOGBOOK_AUDIT_EVENTS',
            ''
        ))))),

        // Block-list: these events are NEVER audited. Merged with the
        // curated per-major exclude list from EventMap.
        //
        // @var list<string>
        'exclude_events' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'LOGBOOK_AUDIT_EXCLUDE_EVENTS',
            ''
        ))))),

        // Fields to ignore when computing entry diffs (high-churn noise).
        // Fields excluded from the recorded diff. `remember_token` matters:
        // Statamic's UserProvider re-saves the user whenever Laravel rotates
        // the remember-me token, which would otherwise log a "User saved"
        // row on a large share of requests. `preferences` is CP UI state
        // (column layouts, start page) the CP writes as a side effect of
        // ordinary browsing — noise, not audit signal.
        // two_factor_* keeps encrypted secrets/recovery codes out of the
        // audit DB and stops the UserSaved diff duplicating the dedicated
        // "Two-factor enabled/disabled" rows.
        'ignore_fields' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'LOGBOOK_AUDIT_IGNORE_FIELDS',
            'updated_at,created_at,date,uri,slug,remember_token,password,last_login,preferences,order,'
                .'two_factor_secret,two_factor_recovery_codes,two_factor_confirmed_at,two_factor_completed'
        ))))),

        // Record sign-in / sign-out / failed-attempt / password-reset rows
        // from Laravel's auth events.
        'auth_events' => (bool) env('LOGBOOK_AUDIT_AUTH_EVENTS', true),

        // Truncate any single value longer than this to avoid bloat.
        'max_value_length' => (int) env('LOGBOOK_AUDIT_MAX_VALUE_LENGTH', 2000),
    ],

    // CP page-view activity ("who opened what"). Off by default; normally
    // managed from the CP settings page, which overrides every key here.
    'activity' => [
        'enabled' => (bool) env('LOGBOOK_ACTIVITY_ENABLED', false),

        // Which CP surfaces to record. null = every tracked page. Keys are the
        // page keys in SettingsRepository::VIEW_PAGES (dashboard, entries,
        // collections, taxonomies, navigation, globals, assets, users,
        // user_listing, forms, blueprints).
        'pages' => null,

        // Repeated opens of the same thing by the same user inside this window
        // collapse into one row. 0 records every open.
        'dedupe_minutes' => (int) env('LOGBOOK_ACTIVITY_DEDUPE_MINUTES', 5),

        // Role handles never tracked. Use '__super' for super admins.
        'excluded_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'LOGBOOK_ACTIVITY_EXCLUDED_ROLES',
            ''
        ))))),

        // Page views are volume, not evidence: they get a shorter retention
        // window than the audit trail, applied by logbook:prune.
        'retention_days' => (int) env('LOGBOOK_ACTIVITY_RETENTION_DAYS', 30),
    ],

    'retention_days' => (int) env('LOGBOOK_RETENTION_DAYS', 365),

    'ingest' => [
        // sync: direct DB writes during request.
        // spool: local NDJSON spool + scheduled flush command.
        'mode' => env('LOGBOOK_INGEST_MODE', 'sync'),
        'spool_path' => env('LOGBOOK_SPOOL_PATH', storage_path('app/logbook/spool')),
        'max_spool_mb' => (int) env('LOGBOOK_SPOOL_MAX_MB', 256),
        // drop_oldest currently supported.
        'backpressure' => env('LOGBOOK_SPOOL_BACKPRESSURE', 'drop_oldest'),
    ],

    'scheduler' => [
        'flush_spool' => [
            // Scheduler activates only when ingest mode is `spool`.
            'enabled' => (bool) env('LOGBOOK_SCHEDULER_FLUSH_SPOOL_ENABLED', true),
            // Interval in minutes for running `logbook:flush-spool`.
            'every_minutes' => (int) env('LOGBOOK_SCHEDULER_FLUSH_SPOOL_EVERY_MINUTES', 60),
            'without_overlapping' => (bool) env('LOGBOOK_SCHEDULER_FLUSH_SPOOL_WITHOUT_OVERLAPPING', true),
        ],
    ],

    'privacy' => [
        'mask_keys' => [
            'password',
            'pass',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'cookie',
            'session',
            'api_key',
            'apikey',
            'secret',
            'client_secret',
        ],
        'mask_value' => '[REDACTED]',
    ],

];
