<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Audit Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default audit driver that will be used to store
    | audit logs.
    |
    | Supported: "mysql"
    |
    */
    'default' => env('AUDIT_DRIVER', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Audit Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the audit drivers for your application.
    |
    */
    'drivers' => [
        'mysql' => [
            'connection' => env('AUDIT_MYSQL_CONNECTION', config('database.default')),
            'table_prefix' => env('AUDIT_TABLE_PREFIX', 'audit_'),
            'table_suffix' => env('AUDIT_TABLE_SUFFIX', '_logs'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure whether audit logs should be processed asynchronously using
    | Laravel's queue system. When enabled, audit logs will be dispatched
    | to the specified queue for background processing.
    |
    */
    'queue' => [
        'enabled' => env('AUDIT_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_QUEUE_CONNECTION', config('queue.default')),
        'queue_name' => env('AUDIT_QUEUE_NAME', 'audit'),
        'delay' => env('AUDIT_QUEUE_DELAY', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Migration
    |--------------------------------------------------------------------------
    |
    | This option allows you to control whether audit tables are automatically
    | created for new entity types when they are first logged.
    |
    */
    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

    /*
    |--------------------------------------------------------------------------
    | Field Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which fields should be excluded from audit logging, and whether
    | to include timestamps in the logged changes.
    |
    */
    'fields' => [
        'exclude' => [
            'password',
            'remember_token',
            'api_token',
            'email_verified_at',
            'password_hash',
            'secret',
            'token',
            'private_key',
            'access_token',
            'refresh_token',
            'api_key',
            'secret_key',
            'stripe_id',
            'pm_type',
            'pm_last_four',
            'trial_ends_at',
        ],
        'include_timestamps' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Causer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the system detects and records the causer of audit events.
    | This is usually the authenticated user, but can be customized.
    |
    */
    'causer' => [
        'guard' => null, // null means use default guard
        'model' => null, // null means auto-detect
        'resolver' => null, // custom resolver class
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup and retention policies for audit logs.
    | You can set global defaults and override them per entity.
    |
    */
    'retention' => [
        'enabled' => env('AUDIT_RETENTION_ENABLED', false),
        'days' => env('AUDIT_RETENTION_DAYS', 365),
        'strategy' => env('AUDIT_RETENTION_STRATEGY', 'delete'), // 'delete', 'archive', 'anonymize'
        'batch_size' => env('AUDIT_RETENTION_BATCH_SIZE', 1000),
        'anonymize_after_days' => env('AUDIT_ANONYMIZE_DAYS', 180),
        'archive_connection' => env('AUDIT_ARCHIVE_CONNECTION', null),
        'run_cleanup_automatically' => env('AUDIT_AUTO_CLEANUP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Entities
    |--------------------------------------------------------------------------
    |
    | List of entities that should be audited. You can override retention
    | settings per entity by adding a 'retention' key.
    |
    */
    'entities' => [
        // Example:
        // \App\Models\User::class => [
        //     'table' => 'users',
        //     'exclude' => ['password'],
        //     'include' => ['*'],
        //     'retention' => [
        //         'days' => 730,
        //         'strategy' => 'anonymize',
        //         'anonymize_after_days' => 365,
        //     ],
        // ],
    ],
];
