# Laravel Audit Log

A comprehensive entity-level audit logging package for Laravel with support for MySQL databases.

## Features

- ✅ **Multiple Entity Support**: Audit any number of entities with dedicated log tables
- ✅ **Database Driver**: Built-in support for MySQL
- ✅ **Model Log Handling**: Automatic tracking of model changes (create, update, delete, restore)
- ✅ **Field Inclusion/Exclusion**: Fine-grained control over which fields to audit
- ✅ **Causer Identification**: Automatic tracking of who made the changes
- ✅ **Auto-Migration**: Automatic table creation for new entities
- ✅ **SOLID Principles**: Clean, maintainable, and extensible architecture

## Requirements

- PHP >= 8.2
- Laravel 11.x or 12.x
- MySQL 8.0+

## Installation

### Step 1: Install the Package

```bash
composer require iamfarhad/laravel-audit-log
```

The package will automatically register its service provider.

### Step 2: Publish the Configuration

```bash
php artisan vendor:publish --tag=audit-logger-config
```

This will create a `config/audit-logger.php` configuration file.

## Configuration

The configuration file (`config/audit-logger.php`) allows you to customize various aspects of the package:

```php
return [
    // Default driver: 'mysql'
    'default' => env('AUDIT_DRIVER', 'mysql'),

    // Driver configurations
    'drivers' => [
        'mysql' => [
            'connection' => env('AUDIT_MYSQL_CONNECTION', config('database.default')),
            'table_prefix' => env('AUDIT_TABLE_PREFIX', 'audit_'),
            'table_suffix' => env('AUDIT_TABLE_SUFFIX', '_logs'),
        ],
    ],

    // Auto-migration for new entities
    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

    // Global field configuration
    'fields' => [
        'exclude' => ['password', 'remember_token', 'api_token'],
        'include_timestamps' => true,
    ],

    // Causer configuration
    'causer' => [
        'guard' => null, // null means use default guard
        'model' => null, // null means auto-detect
        'resolver' => null, // custom resolver class
    ],
];
```

### Helper Methods for Retrieving Logs

You can add helper methods to your models to make retrieving audit logs easier:

```php
/**
 * Get the audit logs for this model.
 */
public function auditLogs(array $options = [])
{
    return AuditLogger::getLogsForEntity(
        entityType: static::class,
        entityId: $this->getKey(),
        options: $options
    );
}

/**
 * Get the most recent audit logs for this model.
 */
public function recentAuditLogs(int $limit = 10)
{
    return AuditLogger::getLogsForEntity(
        entityType: static::class,
        entityId: $this->getKey(),
        options: [
            'limit' => $limit,
            'from_date' => now()->subDays(30)->toDateString(),
        ]
    );
}

/**
 * Get audit logs for a specific action.
 */
public function getActionLogs(string $action)
{
    return AuditLogger::getLogsForEntity(
        entityType: static::class,
        entityId: $this->getKey(),
        options: ['action' => $action]
    );
}
```

## Basic Usage

### Making Models Auditable

To make a model auditable, simply use the `Auditable` trait:

```php
<?php

namespace App\Models;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;

    // Simply define properties to configure auditing
    
    /**
     * Fields to exclude from audit logging.
     *
     * @var array<string>
     */
    protected array $auditExclude = [
        'password',
        'remember_token',
        'updated_at',
    ];
    
    /**
     * Fields to include in audit logging.
     * Default ['*'] means include all fields except excluded ones.
     * If you specify fields here, only these fields will be audited.
     *
     * @var array<string>
     */
    protected array $auditInclude = ['*'];

    /**
     * Get custom metadata for audit logs.
     * Optional - Override this method to add custom metadata.
     */
    public function getAuditMetadata(): array
    {
        return [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }
}
```

### Automatic Logging

Once configured, the model will automatically log:
- **Created** events when a new model is created
- **Updated** events when a model is updated
- **Deleted** events when a model is deleted (including soft deletes)
- **Restored** events when a soft-deleted model is restored

### Manual Logging

For custom actions or to log events not triggered by model events, use the `AuditLogger` facade:

```php
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;

// Log a custom action
AuditLogger::log(
    entityType: Product::class,
    entityId: $product->id,
    action: 'published',
    oldValues: ['status' => 'draft'],
    newValues: ['status' => 'published', 'published_at' => now()->toIso8601String()],
    metadata: [
        'publisher_id' => auth()->id(),
        'publication_channel' => 'web',
    ]
);
```

### Retrieving Audit Logs

```php
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;

// Get all logs for an entity
$logs = AuditLogger::getLogsForEntity(
    entityType: Product::class,
    entityId: $product->id
);

// With filtering options
$logs = AuditLogger::getLogsForEntity(
    entityType: Product::class,
    entityId: $product->id,
    options: [
        'limit' => 10,
        'offset' => 0,
        'action' => 'updated',
        'from_date' => '2024-01-01',
        'to_date' => '2024-12-31',
    ]
);
```

### Disabling Auditing

You can temporarily disable auditing for specific operations:

```php
// Disable for a single model instance
$product = Product::find(1);
$product->disableAuditing();

// Make changes without audit logs
$product->internal_notes = 'System update';
$product->save();

// Re-enable auditing
$product->enableAuditing();
```

## Advanced Features

### Field Filtering

Control which fields are audited using properties:

```php
// Exclude specific fields
protected array $auditExclude = [
    'password', 
    'remember_token', 
    'secret_key'
];

// Or explicitly include only certain fields
protected array $auditInclude = [
    'name', 
    'email', 
    'role', 
    'status'
];
```

The field filtering works as follows:
1. If `$auditInclude` is `['*']` (default), all fields except those in `$auditExclude` will be audited
2. If `$auditInclude` has specific fields, only those fields (minus any in `$auditExclude`) will be audited
3. Global exclusions from `config('audit-logger.fields.exclude')` are always applied

### Custom Metadata

Add context to your audit logs by implementing `getAuditMetadata()`:

```php
public function getAuditMetadata(): array
{
    return [
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'request_id' => request()->header('X-Request-ID'),
        'session_id' => session()->getId(),
    ];
}
```

### Custom Causer Resolver

Create a custom causer resolver if you need special logic for determining who made a change:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

class CustomCauserResolver implements CauserResolverInterface
{
    public function resolve(): array
    {
        // Your custom logic here
        return [
            'type' => User::class,
            'id' => auth()->id() ?? 'system',
        ];
    }
}
```

Register it in the config:

```php
// In config/audit-logger.php
'causer' => [
    'resolver' => \App\Services\CustomCauserResolver::class,
],
```

## Database Schema

### MySQL

For each audited entity, a table is created with the following structure:

```sql
CREATE TABLE audit_products_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    causer_type VARCHAR(255) NULL,
    causer_id VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL,
    INDEX idx_entity_id (entity_id),
    INDEX idx_causer_id (causer_id),
    INDEX idx_created_at (created_at)
);
```

## Troubleshooting

### Common Issues

1. **Tables not created**: The auto-migration only triggers when writing audit logs, not when reading. You have several options:
   
   a. **Manual creation**:
   ```php
   AuditLogger::createStorageForEntity(Product::class);
   ```
   
   b. **Trigger an audit event** to auto-create the table:
   ```php
   $product = Product::first();
   $product->update(['name' => $product->name]);
   ```

2. **Logs not appearing**: Check if:
   - Model uses `Auditable` trait
   - Auditing is enabled on the model instance

## Development

### Testing

Run the tests with:

```bash
composer test
```

### Code Style

This package follows the Laravel coding style. You can check and fix the code style with:

```bash
# Check code style
composer pint:test

# Fix code style issues
composer pint
```

### Static Analysis

Run static analysis with PHPStan:

```bash
composer analyse
```

### Continuous Integration

This package uses GitHub Actions for continuous integration. The following checks are run on each push and pull request:

- **Tests**: PHPUnit tests against multiple PHP and Laravel versions
- **Coding Standards**: Laravel Pint for code style
- **Static Analysis**: PHPStan for static analysis

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.