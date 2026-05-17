# Laravel Audit Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Total Downloads](https://img.shields.io/packagist/dt/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%7C11.x%7C12.x%7C13.x-red.svg?style=flat-square)](https://laravel.com/)
[![License](https://img.shields.io/packagist/l/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)

**Laravel Audit Logger** is a high-performance, compliance-ready audit logging package for Laravel applications. It tracks Eloquent model changes with entity-specific audit tables, source tracking, queue support, retention policies, timeline/diff APIs, restore/replay helpers, field redaction, and optional tamper-evident hash chains.

The package is designed for serious audit trails in SaaS, ecommerce, admin panels, financial systems, and enterprise Laravel apps where audit data must be searchable, understandable, and trustworthy.

## Table of Contents

- [Features](#features)
- [Why Laravel Audit Logger?](#why-laravel-audit-logger)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Advanced Features](#advanced-features)
  - [Audit Search](#audit-search)
  - [Analytics](#analytics)
  - [Timeline and Diff API](#timeline-and-diff-api)
  - [Restore, Replay, and Rollback](#restore-replay-and-rollback)
  - [Tamper-Evident Hash Chain](#tamper-evident-hash-chain)
  - [Field Redaction and Transformers](#field-redaction-and-transformers)
  - [Source Tracking](#source-tracking)
  - [Queue Processing](#queue-processing)
  - [Retention Policies](#retention-policies)
- [Comparison](#comparison)
- [Testing](#testing)
- [Security Best Practices](#security-best-practices)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Entity-specific audit tables**: Stores each model's audit trail in a dedicated table such as `audit_orders_logs` for faster querying and simpler retention.
- **CRUD and restore tracking**: Captures `created`, `updated`, `deleted`, and `restored` model events.
- **Old/new value storage**: Stores before-and-after values in JSON/JSONB columns.
- **Source tracking**: Records whether the change came from an HTTP controller, Artisan command, queue worker, or background job context.
- **User/causer tracking**: Resolves the authenticated user with configurable guard/model/resolver support.
- **Audit search API**: Search audit rows across entity id, action, source, causer, metadata, and changed values.
- **Analytics helpers**: Summary counts, top actions, top causers, top changed entities, and changes per day.
- **Timeline and diff API**: Generate presentation-friendly audit timelines and field-level diffs for admin panels.
- **Restore/replay API**: Restore a model from a previous audit entry or roll it back to old values.
- **Tamper-evident hash chain**: Optional HMAC chain using `audit_hash` and `previous_hash` to detect audit row tampering.
- **Field redaction and transformers**: Mask, hash, redact, or normalize sensitive values before they are stored.
- **Queue support**: Run audit writes synchronously or asynchronously.
- **Retention policies**: Delete, anonymize, or archive old audit logs globally or per model.
- **MySQL and PostgreSQL drivers**: MySQL uses JSON columns; PostgreSQL uses JSONB columns.
- **Modern Laravel support**: Laravel 10, 11, 12, and 13 with PHP 8.1+.

## Why Laravel Audit Logger?

Most Laravel logging packages are optimized for general activity feeds. This package is optimized for audit infrastructure:

- dedicated audit tables per entity for performance and isolation;
- strong compliance features such as retention, anonymization, and hash-chain verification;
- ergonomic APIs for searching, timeline rendering, diffs, and rollback workflows;
- clean extension points for custom causer resolvers, storage drivers, metadata, and field transformers.

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

The package will automatically log model changes into a table such as:

```text
audit_orders_logs
```

Example audit row:

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

A typical configuration looks like this:

```php
return [
    'enabled' => env('AUDIT_ENABLED', true),

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

    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

    'fields' => [
        'exclude' => [
            'password',
            'remember_token',
            'api_token',
            'secret',
            'token',
            'private_key',
            'access_token',
            'refresh_token',
            'api_key',
            'secret_key',
        ],
        'include_timestamps' => true,
        'redact' => [],
        'redaction_replacement' => env('AUDIT_REDACTION_REPLACEMENT', '[REDACTED]'),
        'transformers' => [],
    ],

    'security' => [
        'hashing' => [
            'enabled' => env('AUDIT_HASH_CHAIN_ENABLED', false),
            'algorithm' => env('AUDIT_HASH_ALGORITHM', 'sha256'),
            'key' => env('AUDIT_HASH_KEY', env('APP_KEY')),
        ],
    ],

    'causer' => [
        'guard' => null,
        'model' => null,
        'resolver' => null,
    ],
];
```

## Advanced Features

For the full advanced guide, see [`docs/advanced-audit-features.md`](docs/advanced-audit-features.md).

### Audit Search

Search audit logs with a fluent query API:

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

### Timeline and Diff API

Every auditable model can expose a timeline and field-level diff:

```php
$timeline = $order->auditTimeline();
$latestDiff = $order->auditDiff();
$specificDiff = $order->auditDiff($auditLogId);
```

A timeline entry includes:

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
    'metadata' => [],
    'audit_hash' => '...',
    'previous_hash' => '...',
]
```

### Restore, Replay, and Rollback

Replay the new values from an audit log:

```php
$order->restoreFromAudit($auditLogId);
```

Rollback to the old values captured by an audit log:

```php
$order->rollbackToAudit($auditLogId);
```

Preview a restore/rollback before saving:

```php
$preview = $order->previewRestore($auditLogId, 'rollback');
```

### Tamper-Evident Hash Chain

Enable hash-chain verification:

```env
AUDIT_HASH_CHAIN_ENABLED=true
AUDIT_HASH_ALGORITHM=sha256
AUDIT_HASH_KEY=your-private-audit-key
```

When enabled, every audit row stores:

- `previous_hash`: the previous audit row hash for the same audit table;
- `audit_hash`: an HMAC of the canonical audit payload plus `previous_hash`.

Verify an audit chain:

```php
$result = AuditLogger::verifyHashChain(App\Models\Order::class);

if (! $result['valid']) {
    report($result['failures']);
}
```

### Field Redaction and Transformers

Redact sensitive fields with a fixed replacement:

```php
'fields' => [
    'redact' => ['national_id', 'card_number'],
    'redaction_replacement' => '[REDACTED]',
],
```

Transform fields before storage:

```php
'fields' => [
    'transformers' => [
        'email' => \iamfarhad\LaravelAuditLog\Transformers\MaskEmailTransformer::class,
        'phone' => \iamfarhad\LaravelAuditLog\Transformers\MaskValueTransformer::class,
        'api_token' => \iamfarhad\LaravelAuditLog\Transformers\HashValueTransformer::class,
    ],
],
```

Create a custom transformer:

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

### Source Tracking

The package automatically records where a change came from:

- HTTP controller action, such as `App\Http\Controllers\OrderController@update`
- Artisan command, such as `orders:process`
- Queue/background job context when available

Use source scopes:

```php
$httpLogs = EloquentAuditLog::forEntity(Order::class)->fromHttp()->get();
$consoleLogs = EloquentAuditLog::forEntity(Order::class)->fromConsole()->get();
$commandLogs = EloquentAuditLog::forEntity(Order::class)->fromCommand('orders:process')->get();
```

### Queue Processing

Enable queue processing:

```env
AUDIT_QUEUE_ENABLED=true
AUDIT_QUEUE_CONNECTION=redis
AUDIT_QUEUE_NAME=audit
```

Run an audit worker:

```bash
php artisan queue:work --queue=audit --tries=3 --timeout=60
```

### Retention Policies

The package supports delete, anonymize, and archive strategies:

```php
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

Run cleanup manually:

```bash
php artisan audit:cleanup --dry-run
php artisan audit:cleanup --force
```

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
| Field redactors/transformers | Yes | Custom pipes/manual | Attribute modifiers/resolvers |
| Retention strategies | Delete, anonymize, archive | Manual cleanup | Manual cleanup |

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis and style checks:

```bash
composer analyse
composer pint:test
```

Fix style automatically:

```bash
composer pint
```

## Security Best Practices

- Exclude or redact secrets, tokens, passwords, API keys, and payment fields.
- Use field transformers for PII that must be retained in masked or hashed form.
- Store `AUDIT_HASH_KEY` securely and rotate it intentionally.
- Restrict audit log access with Laravel policies/gates.
- Use retention policies for privacy and compliance requirements.
- Run `AuditLogger::verifyHashChain()` periodically if hash-chain mode is enabled.

## Troubleshooting

### Audit tables are not created

Check:

```php
'audit-logger.auto_migration' => true
```

Or create storage manually through the configured driver.

### Causer is not recorded

Check that the user is authenticated during the operation, and verify your configured guard:

```php
'causer' => [
    'guard' => 'web',
]
```

### Queued logs are missing

Make sure queue workers are running:

```bash
php artisan queue:work --queue=audit
```

### Hash verification fails

Possible causes:

- audit rows were edited manually;
- `AUDIT_HASH_KEY` changed;
- old rows existed before hash-chain mode was enabled;
- records were imported without preserving hash order.

## Contributing

Contributions are welcome.

Before opening a pull request:

```bash
composer install
composer test
composer analyse
composer pint:test
```

Please include tests and documentation for new features.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
