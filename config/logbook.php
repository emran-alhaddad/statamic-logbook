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

    'audit_logs' => [

        // ✅ Allow-list: only these events get recorded.
        // You can delete/keep what you want.
        'events' => [
            // Assets
            \Statamic\Events\AssetContainerBlueprintFound::class,
            \Statamic\Events\AssetContainerCreating::class,
            \Statamic\Events\AssetContainerDeleted::class,
            \Statamic\Events\AssetContainerSaved::class,
            \Statamic\Events\AssetCreated::class,
            \Statamic\Events\AssetCreating::class,
            \Statamic\Events\AssetDeleting::class,
            \Statamic\Events\AssetDeleted::class,
            \Statamic\Events\AssetFolderDeleted::class,
            \Statamic\Events\AssetFolderSaved::class,
            \Statamic\Events\AssetSaved::class,
            \Statamic\Events\AssetSaving::class,
            \Statamic\Events\AssetUploaded::class,

            // Blueprints
            \Statamic\Events\BlueprintCreating::class,
            \Statamic\Events\BlueprintDeleting::class,
            \Statamic\Events\BlueprintDeleted::class,
            \Statamic\Events\BlueprintSaved::class,

            // Collections
            \Statamic\Events\CollectionDeleting::class,
            \Statamic\Events\CollectionDeleted::class,
            \Statamic\Events\CollectionCreated::class,
            \Statamic\Events\CollectionCreating::class,
            \Statamic\Events\CollectionSaved::class,
            \Statamic\Events\CollectionSaving::class,
            \Statamic\Events\CollectionTreeDeleted::class,
            \Statamic\Events\CollectionTreeSaved::class,
            \Statamic\Events\CollectionTreeSaving::class,

            // Entries
            \Statamic\Events\EntryBlueprintFound::class,
            \Statamic\Events\EntryCreated::class,
            \Statamic\Events\EntryCreating::class,
            \Statamic\Events\EntryDeleting::class,
            \Statamic\Events\EntryDeleted::class,
            \Statamic\Events\EntrySaved::class,
            \Statamic\Events\EntrySaving::class,
            \Statamic\Events\EntryScheduleReached::class,

            // Fieldsets
            \Statamic\Events\FieldsetCreating::class,
            \Statamic\Events\FieldsetDeleting::class,
            \Statamic\Events\FieldsetDeleted::class,
            \Statamic\Events\FieldsetSaved::class,

            // Forms
            \Statamic\Events\FormBlueprintFound::class,
            \Statamic\Events\FormCreating::class,
            \Statamic\Events\FormDeleting::class,
            \Statamic\Events\FormDeleted::class,
            \Statamic\Events\FormSaved::class,
            \Statamic\Events\FormSubmitted::class,

            // Glide
            \Statamic\Events\GlideAssetCacheCleared::class,
            \Statamic\Events\GlideCacheCleared::class,
            \Statamic\Events\GlideImageGenerated::class,

            // Globals
            \Statamic\Events\GlobalSetCreating::class,
            \Statamic\Events\GlobalSetDeleting::class,
            \Statamic\Events\GlobalSetDeleted::class,
            \Statamic\Events\GlobalSetSaved::class,

            // Global Variables
            \Statamic\Events\GlobalVariablesCreated::class,
            \Statamic\Events\GlobalVariablesCreating::class,
            \Statamic\Events\GlobalVariablesDeleting::class,
            \Statamic\Events\GlobalVariablesDeleted::class,
            \Statamic\Events\GlobalVariablesSaved::class,
            \Statamic\Events\GlobalVariablesSaving::class,
            \Statamic\Events\GlobalVariablesBlueprintFound::class,

            // Impersonation
            \Statamic\Events\ImpersonationStarted::class,
            \Statamic\Events\ImpersonationEnded::class,

            // Licenses
            \Statamic\Events\LicensesRefreshed::class,
            \Statamic\Events\LicenseSet::class,

            // Localized Terms
            \Statamic\Events\LocalizedTermDeleted::class,
            \Statamic\Events\LocalizedTermSaved::class,

            // Navigation
            \Statamic\Events\NavCreated::class,
            \Statamic\Events\NavCreating::class,
            \Statamic\Events\NavDeleting::class,
            \Statamic\Events\NavDeleted::class,
            \Statamic\Events\NavSaved::class,
            \Statamic\Events\NavSaving::class,
            \Statamic\Events\NavTreeSaved::class,
            \Statamic\Events\NavTreeSaving::class,

            // Response
            \Statamic\Events\ResponseCreated::class,

            // Revisions
            \Statamic\Events\RevisionDeleted::class,
            \Statamic\Events\RevisionSaving::class,
            \Statamic\Events\RevisionSaved::class,

            // Roles
            \Statamic\Events\RoleDeleted::class,
            \Statamic\Events\RoleSaved::class,

            // Search
            \Statamic\Events\SearchIndexUpdated::class,

            // Sites
            \Statamic\Events\SiteCreated::class,
            \Statamic\Events\SiteDeleted::class,
            \Statamic\Events\SiteSaved::class,

            // Stache / Static Cache
            \Statamic\Events\StacheCleared::class,
            \Statamic\Events\StacheWarmed::class,
            \Statamic\Events\StaticCacheCleared::class,

            // Submissions
            \Statamic\Events\SubmissionCreated::class,
            \Statamic\Events\SubmissionCreating::class,
            \Statamic\Events\SubmissionDeleted::class,
            \Statamic\Events\SubmissionSaved::class,

            // Taxonomy
            \Statamic\Events\TaxonomyCreating::class,
            \Statamic\Events\TaxonomyDeleting::class,
            \Statamic\Events\TaxonomyDeleted::class,
            \Statamic\Events\TaxonomySaved::class,

            // Terms
            \Statamic\Events\TermBlueprintFound::class,
            \Statamic\Events\TermCreating::class,
            \Statamic\Events\TermDeleting::class,
            \Statamic\Events\TermDeleted::class,
            \Statamic\Events\TermSaved::class,

            // Users / Groups
            \Statamic\Events\UserBlueprintFound::class,
            \Statamic\Events\UserCreating::class,
            \Statamic\Events\UserDeleting::class,
            \Statamic\Events\UserDeleted::class,
            \Statamic\Events\UserGroupDeleted::class,
            \Statamic\Events\UserGroupSaved::class,
            \Statamic\Events\UserPasswordChanged::class,
            \Statamic\Events\UserRegistering::class,
            \Statamic\Events\UserRegistered::class,
            \Statamic\Events\UserSaved::class,

            // Url / Caches
            \Statamic\Events\UrlInvalidated::class,
        ],

        // fields to ignore when computing diffs (noise)
        'ignore_fields' => array_filter(array_map('trim', explode(',', env(
            'LOGBOOK_AUDIT_IGNORE_FIELDS',
            'updated_at,created_at,date,uri,slug'
        )))),

        // avoid huge payloads in DB
        'max_value_length' => (int) env('LOGBOOK_AUDIT_MAX_VALUE_LENGTH', 2000),
    ],

    'retention_days' => (int) env('LOGBOOK_RETENTION_DAYS', 365),
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
