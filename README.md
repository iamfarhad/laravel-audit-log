# Laravel Audit Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Total Downloads](https://img.shields.io/packagist/dt/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%7C11.x%7C12.x%7C13.x-red.svg?style=flat-square)](https://laravel.com/)
[![License](https://img.shields.io/packagist/l/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)

**Laravel Audit Logger** is a compliance-ready audit trail package for Laravel. It stores model changes in dedicated audit tables, supports searchable timelines and diffs, provides restore/replay helpers, protects sensitive fields, and can verify tamper-evident hash chains.

The v2 infrastructure adds production migration tooling, native batch inserts, multi-tenancy, append-only audit rows, relationship auditing helpers, operational Artisan commands, API resources, authorization helpers, snapshots, and lifecycle events.

Use it when audit data is more than a simple activity feed: SaaS platforms, marketplaces, fintech/admin systems, back-office tools, enterprise applications, and any Laravel app where changes must be explainable, searchable, and trustworthy.

## Table of Contents

- [Highlights](#highlights)
- [Why this package?](#why-this-package)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Production Table Management](#production-table-management)
- [Search, Analytics, Timeline, and Diff](#search-analytics-timeline-and-diff)
- [Restore, Replay, and Rollback](#restore-replay-and-rollback)
- [Security and Integrity](#security-and-integrity)
- [Privacy: Redaction and Transformers](#privacy-redaction-and-transformers)
- [Multi-Tenancy](#multi-tenancy)
- [Batch Inserts](#batch-inserts)
- [Relationship Auditing](#relationship-auditing)
- [Snapshots](#snapshots)
- [API Resources](#api-resources)
- [Operational Commands](#operational-commands)
- [Retention Policies](#retention-policies)
- [Events](#events)
- [Upgrade Notes](#upgrade-notes)
- [Comparison](#comparison)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## Highlights

- **Entity-specific audit tables**: Store each model's audit history in its own table, such as `audit_orders_logs`.
- **Automatic model auditing**: Capture created, updated, deleted, restored, and custom relationship audit actions.
- **Searchable audit trails**: Search by action, source, causer, entity id, metadata, old values, and new values.
- **Analytics helpers**: Build dashboards with summaries, top actions, top causers, changed entities, and daily activity.
- **Timeline and diff APIs**: Render admin-friendly history views and field-level changes.
- **Restore/replay/rollback APIs**: Restore a model from audit history or preview changes before saving.
- **Tamper-evident hash chain**: Optional HMAC chain using `audit_hash` and `previous_hash`.
- **Append-only mode**: Prevent Eloquent updates and deletes on audit log rows.
- **Privacy controls**: Exclude, redact, mask, hash, or transform sensitive fields before storage.
- **Production migration tooling**: Generate and create audit tables with Artisan commands.
- **Multi-tenancy**: Store tenant context on every audit row when enabled.
- **Native batch inserts**: Insert large audit batches efficiently when queueing and hash-chain mode are off.
- **Relationship auditing**: Explicit helpers for attach, detach, and sync workflows.
- **API resources**: Consistent JSON resources for audit logs, timelines, and diffs.
- **Operational commands**: Doctor checks, config validation, stats, timeline, diff, verify, upgrade, and partition guidance.
- **MySQL and PostgreSQL support**: JSON for MySQL and JSONB for PostgreSQL.

## Why this package?

Most Laravel activity packages are optimized for feeds. Laravel Audit Logger is optimized for **audit infrastructure**:

- dedicated audit tables improve isolation and long-term query performance;
- source tracking explains where a change came from;
- restore and rollback APIs support operational recovery workflows;
- hash-chain verification helps detect tampering;
- v2 commands help production teams validate, migrate, inspect, and operate audit tables;
- sensitive data controls reduce accidental PII or secret exposure.

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, 12.x, or 13.x
- MySQL 8.0+ or PostgreSQL

## Installation

Install the package with Composer:

```bash
composer require iamfarhad/laravel-audit-log
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=audit-logger-config
```

This creates:

```text
config/audit-logger.php
```

## Quick Start

Add the `Auditable` trait to any Eloquent model:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    use Auditable;

    protected $fillable = [
        'customer_id',
        'total',
        'status',
    ];
}
```

After that, create/update/delete/restore operations are recorded automatically.

By default, the package stores order audit rows in:

```text
audit_orders_logs
```

A typical audit row contains:

```php
[
    'entity_id' => '1',
    'action' => 'updated',
    'old_values' => ['status' => 'pending'],
    'new_values' => ['status' => 'approved'],
    'causer_type' => App\Models\User::class,
    'causer_id' => 10,
    'source' => 'App\Http\Controllers\OrderController@approve',
    'created_at' => '2026-05-18 10:30:00',
]
```

## Configuration

A compact configuration example:

```php
return [
    'enabled' => env('AUDIT_ENABLED', true),
    'preset' => env('AUDIT_PRESET', 'basic'),
    'default' => env('AUDIT_DRIVER', 'mysql'),
    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

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
        'columns' => ['type' => 'tenant_type', 'id' => 'tenant_id'],
    ],

    'fields' => [
        'exclude' => ['password', 'remember_token', 'api_token', 'secret', 'token'],
        'include_timestamps' => true,
        'redact' => [],
        'redaction_replacement' => env('AUDIT_REDACTION_REPLACEMENT', '[REDACTED]'),
        'transformers' => [],
    ],

    'entities' => [
        // App\Models\Order::class => [
        //     'audit_table' => 'audit_orders_logs',
        //     'exclude' => ['internal_notes'],
        //     'relations' => ['items', 'tags'],
        // ],
    ],
];
```

## Production Table Management

Runtime auto-migration is convenient for local development. Production apps should prefer generated migrations or explicit table creation.

Generate a migration for one audited model:

```bash
php artisan audit:make-migration App\Models\Order
```

Create audit tables for all configured entities:

```bash
php artisan audit:migrate
```

Create storage for one entity:

```bash
php artisan audit:migrate --entity=App\Models\Order
```

Disable runtime auto-migration in production after your migrations are deployed:

```env
AUDIT_AUTO_MIGRATION=false
```

The v2 audit table shape includes optional support columns:

```text
id
entity_id
action
old_values
new_values
changes
causer_type
causer_id
tenant_type
tenant_id
metadata
created_at
source
audit_hash
previous_hash
anonymized_at
```

## Search, Analytics, Timeline, and Diff

### Search

```php
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;

$logs = AuditLogger::search(App\Models\Order::class, 'approved')
    ->forAction('updated')
    ->between(now()->subMonth(), now())
    ->latest()
    ->paginate(50);
```

Build a query without a keyword:

```php
$logs = AuditLogger::query(App\Models\Order::class)
    ->forEntityId($order->id)
    ->forCauser(App\Models\User::class, auth()->id())
    ->get();
```

### Analytics

```php
$summary = AuditLogger::analytics()->summary(App\Models\Order::class);
$topActions = AuditLogger::analytics()->topActions(App\Models\Order::class);
$topCausers = AuditLogger::analytics()->topCausers(App\Models\Order::class);
$topChangedEntities = AuditLogger::analytics()->topChangedEntities(App\Models\Order::class);
$changesPerDay = AuditLogger::analytics()->changesPerDay(App\Models\Order::class, 30);
```

### Timeline

```php
$timeline = $order->auditTimeline();
```

Timeline entries are suitable for admin panels:

```php
[
    'id' => 100,
    'action' => 'updated',
    'entity_id' => '1',
    'causer_type' => App\Models\User::class,
    'causer_id' => 10,
    'source' => 'App\Http\Controllers\OrderController@update',
    'created_at' => '2026-05-18 10:30:00',
    'changes' => [
        ['field' => 'status', 'old' => 'pending', 'new' => 'approved'],
    ],
]
```

### Diff

```php
$latestDiff = $order->auditDiff();
$specificDiff = $order->auditDiff($auditLogId);
```

## Restore, Replay, and Rollback

Restore/replay new values from an audit log:

```php
$order->restoreFromAudit($auditLogId);
```

Rollback to old values:

```php
$order->rollbackToAudit($auditLogId);
```

Preview before saving:

```php
$preview = $order->previewRestore($auditLogId, 'rollback');
```

Restore safety options:

```env
AUDIT_RESTORE_VALIDATE_FILLABLE=true
AUDIT_RESTORE_AUDIT=true
```

- `AUDIT_RESTORE_VALIDATE_FILLABLE=true` only applies fields listed in the model's `$fillable` array.
- `AUDIT_RESTORE_AUDIT=false` saves the restored model without creating another audit row.
- Authorization can be enabled with `AUDIT_AUTHORIZATION_ENABLED=true`.

## Security and Integrity

### Append-only mode

```env
AUDIT_APPEND_ONLY=true
```

When enabled, Eloquent update/delete operations on `EloquentAuditLog` are blocked.

### Hash-chain mode

Hash-chain mode is disabled by default and must be enabled explicitly:

```env
AUDIT_HASH_CHAIN_ENABLED=true
AUDIT_HASH_ALGORITHM=sha256
AUDIT_HASH_KEY=your-private-audit-key
```

When enabled, every audit row stores:

- `previous_hash`: previous row hash in the same audit table;
- `audit_hash`: HMAC of the canonical audit payload plus `previous_hash`.

Verify a chain:

```php
$result = AuditLogger::verifyHashChain(App\Models\Order::class);

if (! $result['valid']) {
    report($result['failures']);
}
```

Or use Artisan:

```bash
php artisan audit:verify App\Models\Order
php artisan audit:verify App\Models\Order --entity-id=123
php artisan audit:verify App\Models\Order --fail-on-missing-hash
```

Native batch inserts are automatically skipped when hash-chain mode is enabled, because each row must be chained sequentially.

## Privacy: Redaction and Transformers

Exclude fields completely:

```php
'fields' => [
    'exclude' => ['password', 'remember_token', 'api_token'],
],
```

Redact fields with a fixed replacement:

```php
'fields' => [
    'redact' => ['national_id', 'card_number'],
    'redaction_replacement' => '[REDACTED]',
],
```

Transform values before storage:

```php
'fields' => [
    'transformers' => [
        'email' => \iamfarhad\LaravelAuditLog\Transformers\MaskEmailTransformer::class,
        'phone' => \iamfarhad\LaravelAuditLog\Transformers\MaskValueTransformer::class,
        'api_token' => \iamfarhad\LaravelAuditLog\Transformers\HashValueTransformer::class,
    ],
],
```

Custom transformer:

```php
<?php

declare(strict_types=1);

namespace App\Audit;

use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use Illuminate\Database\Eloquent\Model;

final class MoneyTransformer implements FieldTransformerInterface
{
    public function transform(string $field, mixed $value, string $direction, Model $model): mixed
    {
        return number_format((float) $value, 2, '.', '');
    }
}
```

## Multi-Tenancy

Enable tenant context:

```env
AUDIT_TENANT_ENABLED=true
```

Create a resolver:

```php
<?php

declare(strict_types=1);

namespace App\Audit;

use iamfarhad\LaravelAuditLog\Contracts\TenantResolverInterface;

final class CurrentTenantResolver implements TenantResolverInterface
{
    public function resolve(): array
    {
        $tenant = tenant();

        return [
            'type' => $tenant?->getMorphClass(),
            'id' => $tenant?->getKey(),
        ];
    }
}
```

Configure it:

```php
'tenant' => [
    'enabled' => true,
    'resolver' => App\Audit\CurrentTenantResolver::class,
    'columns' => [
        'type' => 'tenant_type',
        'id' => 'tenant_id',
    ],
],
```

Query by tenant:

```php
$logs = EloquentAuditLog::forEntity(Order::class)
    ->forTenant($tenant->id, $tenant::class)
    ->latest()
    ->get();
```

## Batch Inserts

Batch mode is useful for imports, backfills, and high-volume programmatic audit creation.

```env
AUDIT_BATCH_ENABLED=true
AUDIT_BATCH_SIZE=500
AUDIT_QUEUE_ENABLED=false
```

```php
AuditLogger::batch([
    AuditLog::fromArray([...]),
    AuditLog::fromArray([...]),
]);
```

Rules:

- native batch mode only runs when queueing is disabled;
- native batch mode is skipped when hash-chain mode is enabled;
- rows are grouped by audit table and inserted in chunks.

## Relationship Auditing

The package provides explicit helpers for relation changes. It does not monkey-patch Laravel relationship methods.

```php
use iamfarhad\LaravelAuditLog\Services\RelationshipAuditor;

app(RelationshipAuditor::class)->attached($user, 'roles', [$roleId]);
app(RelationshipAuditor::class)->detached($user, 'roles', [$roleId]);
app(RelationshipAuditor::class)->synced($user, 'roles', $roleIds);
```

Example actions:

```text
roles_attached
roles_detached
roles_synced
```

## Snapshots

Snapshots let you periodically store a full model state for faster reconstruction.

```env
AUDIT_SNAPSHOTS_ENABLED=true
AUDIT_SNAPSHOT_EVERY=20
```

Manual snapshot:

```php
app(\iamfarhad\LaravelAuditLog\Services\AuditSnapshotService::class)->snapshot($order);
```

Automatic snapshot helper:

```php
app(\iamfarhad\LaravelAuditLog\Services\AuditSnapshotService::class)->maybeSnapshot($order);
```

Snapshot rows use action:

```text
snapshot
```

## API Resources

Use resources to expose audit data from your own controllers:

```php
use iamfarhad\LaravelAuditLog\Http\Resources\AuditLogResource;
use iamfarhad\LaravelAuditLog\Http\Resources\AuditTimelineResource;
use iamfarhad\LaravelAuditLog\Http\Resources\AuditDiffResource;

return AuditLogResource::collection(
    AuditLogger::query(Order::class)->latest()->paginate()
);
```

Timeline and diff resources are available for UI/API responses:

```php
return AuditTimelineResource::collection($order->auditTimeline());
return AuditDiffResource::collection($order->auditDiff($auditLogId));
```

## Operational Commands

| Command | Purpose |
|---|---|
| `audit:make-migration App\Models\Order` | Generate a production audit table migration |
| `audit:migrate` | Create audit storage for configured entities |
| `audit:migrate --entity=App\Models\Order` | Create storage for one entity |
| `audit:config-check` | Validate audit config, transformers, resolver, and hash settings |
| `audit:doctor` | Check configured entities and audit tables |
| `audit:stats App\Models\Order` | Show summary statistics |
| `audit:timeline App\Models\Order 123` | Print timeline rows for one entity id |
| `audit:diff App\Models\Order 456` | Print field-level changes for one audit row |
| `audit:verify App\Models\Order` | Verify hash-chain integrity |
| `audit:partition audit_orders_logs --monthly` | Show partitioning guidance |
| `audit:upgrade v1-to-v2 --dry-run` | Report missing v2 columns |
| `audit:cleanup --dry-run` | Preview retention cleanup |

## Retention Policies

Configure global or per-entity retention.

```php
'retention' => [
    'enabled' => env('AUDIT_RETENTION_ENABLED', false),
    'days' => env('AUDIT_RETENTION_DAYS', 365),
    'strategy' => env('AUDIT_RETENTION_STRATEGY', 'delete'),
    'batch_size' => env('AUDIT_RETENTION_BATCH_SIZE', 1000),
],

'entities' => [
    App\Models\User::class => [
        'retention' => [
            'enabled' => true,
            'days' => 730,
            'strategy' => 'anonymize',
            'anonymize_after_days' => 365,
        ],
    ],
],
```

Run cleanup:

```bash
php artisan audit:cleanup --dry-run
php artisan audit:cleanup --force
```

## Events

Listen to lifecycle events:

```php
use iamfarhad\LaravelAuditLog\Events\AuditCreated;
use iamfarhad\LaravelAuditLog\Events\AuditCreating;
use iamfarhad\LaravelAuditLog\Events\AuditVerificationFailed;

Event::listen(AuditCreated::class, function (AuditCreated $event) {
    // Send to internal security pipeline, metrics, or notifications.
});
```

Available events:

- `AuditCreating`
- `AuditCreated`
- `AuditVerificationFailed`

## Upgrade Notes

Check missing v2 columns:

```bash
php artisan audit:upgrade v1-to-v2 --dry-run
```

The command checks for:

```text
audit_hash
previous_hash
tenant_type
tenant_id
changes
```

Example migration for existing audit tables:

```php
Schema::table('audit_orders_logs', function (Blueprint $table): void {
    $table->json('changes')->nullable()->after('new_values');
    $table->string('tenant_type')->nullable()->after('causer_id');
    $table->string('tenant_id')->nullable()->after('tenant_type');
    $table->string('audit_hash', 128)->nullable()->after('source');
    $table->string('previous_hash', 128)->nullable()->after('audit_hash');

    $table->index('tenant_id');
    $table->index(['tenant_id', 'created_at']);
    $table->index('audit_hash');
    $table->index('previous_hash');
});
```

Recommended production upgrade order:

1. Deploy code with new features disabled.
2. Run `audit:upgrade v1-to-v2 --dry-run`.
3. Add missing nullable columns through migrations.
4. Enable tenant/change/snapshot features as needed.
5. Enable hash-chain mode only after hash columns exist.
6. Run `audit:doctor` and `audit:config-check`.
7. Verify CI and monitor audit writes.

## Comparison

| Capability | Laravel Audit Logger | Spatie Activitylog | Laravel Auditing |
|---|---:|---:|---:|
| Entity-specific audit tables | Yes | No | No |
| MySQL storage | Yes | Yes | Yes |
| PostgreSQL storage | Yes | Via database support | Via database support |
| Queue support | Yes | App-managed | App-managed |
| Source tracking | Yes | Partial/manual | Resolver-based |
| Audit search API | Yes | Query activity model | Query audit model |
| Analytics helpers | Yes | No built-in analytics helper | No built-in analytics helper |
| Timeline/diff API | Yes | Manual formatting | Auditable transition data |
| Restore/replay API | Yes | No built-in model restore | Limited/manual workflows |
| Tamper-evident hash chain | Yes | No | No |
| Append-only mode | Yes | No | No |
| Multi-tenancy context | Yes | Manual | Manual/resolver-based |
| Native batch insert helper | Yes | No package-level helper | No package-level helper |
| Relationship audit helper | Yes | Manual | Manual/custom |
| API resources | Yes | No | No |
| Field redactors/transformers | Yes | Custom pipes/manual | Attribute modifiers/resolvers |
| Retention strategies | Delete, anonymize, archive | Manual cleanup | Manual cleanup |
| Operational doctor command | Yes | No | No |

## Testing

Run tests:

```bash
composer test
```

Run style checks:

```bash
composer pint:test
```

Fix style:

```bash
composer pint
```

If your project has static analysis configured:

```bash
composer analyse
```

## Troubleshooting

### Audit tables are not created

For development, enable auto-migration:

```env
AUDIT_AUTO_MIGRATION=true
```

For production, generate and run migrations:

```bash
php artisan audit:make-migration App\Models\Order
php artisan migrate
```

Or create storage explicitly:

```bash
php artisan audit:migrate --entity=App\Models\Order
```

### Hash verification says columns are missing

Add nullable `audit_hash` and `previous_hash` columns before enabling hash-chain verification.

### Batch inserts are not used

Native batch insert is intentionally skipped when:

- `AUDIT_QUEUE_ENABLED=true`; or
- `AUDIT_HASH_CHAIN_ENABLED=true`; or
- `AUDIT_BATCH_ENABLED=false`.

### Tenant fields are not stored

Check all three:

- `AUDIT_TENANT_ENABLED=true`
- resolver implements `TenantResolverInterface`
- audit table has `tenant_type` and `tenant_id` columns

### Restore is blocked

If authorization is enabled, define the configured gate:

```php
Gate::define('restoreFromAudit', fn ($user, $model) => $user->can('update', $model));
```

### Queued logs are missing

Run an audit queue worker:

```bash
php artisan queue:work --queue=audit --tries=3 --timeout=60
```

## Documentation

- [Advanced audit features](docs/advanced-audit-features.md)
- [v2 major release infrastructure](docs/v2-major-release.md)

## Contributing

Contributions are welcome.

Before opening a pull request:

```bash
composer install
composer test
composer pint:test
```

Please include tests and documentation for new features.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
