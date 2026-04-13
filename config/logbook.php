<?php

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
        'ignore_channels' => array_filter(array_map('trim', explode(',', env(
            'LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS',
            'deprecations'
        )))),
        'ignore_message_contains' => array_filter(array_map('trim', explode(',', env(
            'LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES',
            'Since symfony/http-foundation,Unable to create configured logger. Using emergency logger.'
        )))),
    ],

    'audit_logs' => [
        // Curated defaults only. Set true to merge in discovered Statamic events.
        'discover_events' => (bool) env('LOGBOOK_AUDIT_DISCOVER_EVENTS', false),

        // Curated high-signal mutation events (default allow-list).
        'events' => [
            // Entries
            \Statamic\Events\EntryCreated::class,
            \Statamic\Events\EntryDeleted::class,
            \Statamic\Events\EntrySaved::class,
            \Statamic\Events\EntrySaving::class,

            // Taxonomy / terms
            \Statamic\Events\TaxonomyDeleted::class,
            \Statamic\Events\TaxonomySaved::class,
            \Statamic\Events\TermDeleted::class,
            \Statamic\Events\TermSaved::class,

            // Global content / navigation
            \Statamic\Events\GlobalSetDeleted::class,
            \Statamic\Events\GlobalSetSaved::class,
            \Statamic\Events\GlobalVariablesDeleted::class,
            \Statamic\Events\GlobalVariablesSaved::class,
            \Statamic\Events\NavDeleted::class,
            \Statamic\Events\NavSaved::class,
            \Statamic\Events\NavTreeSaved::class,

            // User/security actions
            \Statamic\Events\ImpersonationStarted::class,
            \Statamic\Events\ImpersonationEnded::class,
            \Statamic\Events\UserDeleted::class,
            \Statamic\Events\UserSaved::class,
            \Statamic\Events\UserPasswordChanged::class,
            \Statamic\Events\UserGroupDeleted::class,
            \Statamic\Events\UserGroupSaved::class,
            \Statamic\Events\RoleDeleted::class,
            \Statamic\Events\RoleSaved::class,
        ],

        // Block-list: events you do NOT want to audit.
        'exclude_events' => array_values(array_unique(array_merge([
            // Keep excluded (high-noise / low-audit value):
            \Statamic\Events\AssetContainerBlueprintFound::class,
            \Statamic\Events\EntryBlueprintFound::class,
            \Statamic\Events\FormBlueprintFound::class,
            \Statamic\Events\GlobalVariablesBlueprintFound::class,
            \Statamic\Events\TermBlueprintFound::class,
            \Statamic\Events\UserBlueprintFound::class,
            \Statamic\Events\ResponseCreated::class,
            \Statamic\Events\GlideAssetCacheCleared::class,
            \Statamic\Events\GlideCacheCleared::class,
            \Statamic\Events\GlideImageGenerated::class,
            \Statamic\Events\StacheCleared::class,
            \Statamic\Events\StacheWarmed::class,
            \Statamic\Events\StaticCacheCleared::class,
            // Optional lower-signal defaults:
            \Statamic\Events\SearchIndexUpdated::class,
            \Statamic\Events\UrlInvalidated::class,
        ], array_filter(array_map('trim', explode(',', env(
            'LOGBOOK_AUDIT_EXCLUDE_EVENTS',
            ''
        ))))))),

        // fields to ignore when computing diffs (noise)
        'ignore_fields' => array_filter(array_map('trim', explode(',', env(
            'LOGBOOK_AUDIT_IGNORE_FIELDS',
            'updated_at,created_at,date,uri,slug'
        )))),

        // avoid huge payloads in DB
        'max_value_length' => (int) env('LOGBOOK_AUDIT_MAX_VALUE_LENGTH', 2000),
    ],

    'retention_days' => (int) env('LOGBOOK_RETENTION_DAYS', 365),

    'ingest' => [
        // sync: direct DB writes during request
        // spool: local NDJSON spool + scheduled flush command
        'mode' => env('LOGBOOK_INGEST_MODE', 'sync'),
        'spool_path' => env('LOGBOOK_SPOOL_PATH', storage_path('app/logbook/spool')),
        'max_spool_mb' => (int) env('LOGBOOK_SPOOL_MAX_MB', 256),
        // drop_oldest currently supported
        'backpressure' => env('LOGBOOK_SPOOL_BACKPRESSURE', 'drop_oldest'),
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
