<?php

declare(strict_types=1);

return [
    'enabled' => env('AUDIT_ENABLED', true),

    'preset' => env('AUDIT_PRESET', 'basic'),

    'default' => env('AUDIT_DRIVER', 'mysql'),

    'drivers' => [
        'mysql' => [
            'connection' => env('AUDIT_MYSQL_CONNECTION', config('database.default')),
            'table_prefix' => env('AUDIT_TABLE_PREFIX', 'audit_'),
            'table_suffix' => env('AUDIT_TABLE_SUFFIX', '_logs'),
        ],

        'postgresql' => [
            'connection' => env('AUDIT_PGSQL_CONNECTION', config('database.default')),
            'table_prefix' => env('AUDIT_TABLE_PREFIX', 'audit_'),
            'table_suffix' => env('AUDIT_TABLE_SUFFIX', '_logs'),
        ],
    ],

    'queue' => [
        'enabled' => env('AUDIT_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_QUEUE_CONNECTION', config('queue.default')),
        'queue_name' => env('AUDIT_QUEUE_NAME', 'audit'),
        'delay' => env('AUDIT_QUEUE_DELAY', 0),
    ],

    'batch' => [
        'enabled' => env('AUDIT_BATCH_ENABLED', false),
        'size' => env('AUDIT_BATCH_SIZE', 500),
    ],

    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

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
        'redact' => [],
        'redaction_replacement' => env('AUDIT_REDACTION_REPLACEMENT', '[REDACTED]'),
        'transformers' => [
            // 'email' => \iamfarhad\LaravelAuditLog\Transformers\MaskEmailTransformer::class,
            // 'phone' => \iamfarhad\LaravelAuditLog\Transformers\MaskValueTransformer::class,
        ],
    ],

    'security' => [
        'append_only' => env('AUDIT_APPEND_ONLY', false),
        'hashing' => [
            'enabled' => env('AUDIT_HASH_CHAIN_ENABLED', false),
            'algorithm' => env('AUDIT_HASH_ALGORITHM', 'sha256'),
            'key' => env('AUDIT_HASH_KEY', env('APP_KEY')),
        ],
    ],

    'tenant' => [
        'enabled' => env('AUDIT_TENANT_ENABLED', false),
        'resolver' => \iamfarhad\LaravelAuditLog\Services\TenantResolver::class,
        'columns' => [
            'type' => 'tenant_type',
            'id' => 'tenant_id',
        ],
    ],

    'authorization' => [
        'enabled' => env('AUDIT_AUTHORIZATION_ENABLED', false),
        'view_gate' => 'viewAuditLogs',
        'restore_gate' => 'restoreFromAudit',
    ],

    'changes' => [
        'store' => env('AUDIT_STORE_CHANGES', false),
        'column' => 'changes',
    ],

    'snapshots' => [
        'enabled' => env('AUDIT_SNAPSHOTS_ENABLED', false),
        'every' => env('AUDIT_SNAPSHOT_EVERY', 20),
    ],

    'restore' => [
        'validate_fillable' => env('AUDIT_RESTORE_VALIDATE_FILLABLE', false),
        'audit_restores' => env('AUDIT_RESTORE_AUDIT', true),
    ],

    'causer' => [
        'guard' => null,
        'model' => null,
        'resolver' => null,
    ],

    'retention' => [
        'enabled' => env('AUDIT_RETENTION_ENABLED', false),
        'days' => env('AUDIT_RETENTION_DAYS', 365),
        'strategy' => env('AUDIT_RETENTION_STRATEGY', 'delete'),
        'batch_size' => env('AUDIT_RETENTION_BATCH_SIZE', 1000),
        'anonymize_after_days' => env('AUDIT_ANONYMIZE_DAYS', 180),
        'archive_connection' => env('AUDIT_ARCHIVE_CONNECTION', null),
        'run_cleanup_automatically' => env('AUDIT_AUTO_CLEANUP', false),
    ],

    'entities' => [
        // \App\Models\User::class => [
        //     'table' => 'users',
        //     'audit_table' => 'audit_users_logs',
        //     'exclude' => ['password'],
        //     'include' => ['*'],
        //     'relations' => ['roles', 'permissions'],
        //     'retention' => [
        //         'days' => 730,
        //         'strategy' => 'anonymize',
        //         'anonymize_after_days' => 365,
        //     ],
        // ],
    ],
];
