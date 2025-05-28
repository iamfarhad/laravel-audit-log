# Laravel Audit Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/iamfarhad/laravel-audit-log/run-tests?label=tests)](https://github.com/iamfarhad/laravel-audit-log/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/iamfarhad/laravel-audit-log/Check%20&%20fix%20styling?label=code%20style)](https://github.com/iamfarhad/laravel-audit-log/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)

A comprehensive entity-level audit logging package for Laravel with support for MySQL and MongoDB databases.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
  - [Making Models Auditable](#making-models-auditable)
  - [Automatic Logging](#automatic-logging)
  - [Manual Logging](#manual-logging)
  - [Retrieving Audit Logs](#retrieving-audit-logs)
  - [Disabling Auditing](#disabling-auditing)
- [Advanced Features](#advanced-features)
  - [Custom Metadata](#custom-metadata)
  - [Field Filtering](#field-filtering)
  - [Batch Processing](#batch-processing)
  - [Custom Causer Resolver](#custom-causer-resolver)
  - [Using Different Drivers](#using-different-drivers)
  - [Event-Driven Architecture](#event-driven-architecture)
- [Database Schema](#database-schema)
- [Troubleshooting](#troubleshooting)
- [Performance Considerations](#performance-considerations)
- [Testing](#testing)
- [Changelog](#changelog)
- [License](#license)

## Features

- ✅ **Multiple Entity Support**: Audit any number of entities with dedicated log tables/collections
- ✅ **Database Drivers**: Built-in support for MySQL and MongoDB
- ✅ **Model Log Handling**: Automatic tracking of model changes (create, update, delete, restore)
- ✅ **Field Inclusion/Exclusion**: Fine-grained control over which fields to audit
- ✅ **Causer Identification**: Automatic tracking of who made the changes
- ✅ **Auto-Migration**: Automatic table/collection creation for new entities
- ✅ **Batch Processing**: Optional batching of audit logs for improved performance
- ✅ **Queue Support**: Option to process audit logs via Laravel queues
- ✅ **SOLID Principles**: Clean, maintainable, and extensible architecture

## Requirements

- PHP >= 8.2
- Laravel 11.x or 12.x
- MySQL 8.0+ (for MySQL driver)
- MongoDB 4.4+ (for MongoDB driver, optional)

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

### Step 3: Optional - Install MongoDB Support

If you plan to use MongoDB as a driver, install the MongoDB Laravel package:

```bash
composer require mongodb/laravel-mongodb
```

## Configuration

The configuration file (`config/audit-logger.php`) allows you to customize various aspects of the package:

```php
return [
    // Default driver: 'mysql' or 'mongodb'
    'default' => env('AUDIT_DRIVER', 'mysql'),

    // Driver configurations
    'drivers' => [
        'mysql' => [
            'connection' => env('AUDIT_MYSQL_CONNECTION', config('database.default')),
            'table_prefix' => env('AUDIT_TABLE_PREFIX', 'audit_'),
            'table_suffix' => env('AUDIT_TABLE_SUFFIX', '_logs'),
        ],
        'mongodb' => [
            'connection' => env('AUDIT_MONGODB_CONNECTION', 'mongodb'),
            'collection_prefix' => env('AUDIT_COLLECTION_PREFIX', 'audit_'),
            'collection_suffix' => env('AUDIT_COLLECTION_SUFFIX', '_logs'),
        ],
    ],

    // Auto-migration for new entities
    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

    // Batch processing configuration
    'batch' => [
        'enabled' => env('AUDIT_BATCH_ENABLED', false),
        'size' => env('AUDIT_BATCH_SIZE', 50),
        'timeout' => env('AUDIT_BATCH_TIMEOUT', 10), // seconds
    ],

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

## Basic Usage

### Making Models Auditable

To make a model auditable, implement the `AuditableInterface` and use the `Auditable` trait:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use iamfarhad\LaravelAuditLog\Contracts\AuditableInterface;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements AuditableInterface
{
    use Auditable;

    // Your model code...

    /**
     * Get fields to exclude from audit logging.
     *
     * @return array<string>
     */
    public function getAuditExclude(): array
    {
        return [
            'internal_notes',
            'updated_at',
        ];
    }

    /**
     * Get custom metadata for audit logs.
     *
     * @return array<string, mixed>
     */
    public function getAuditMetadata(): array
    {
        return [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }

    /**
     * Determine if auditing should be queued.
     */
    public function shouldQueueAudit(): bool
    {
        return true; // Process audit logs in queue
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
        'order_by' => 'created_at',
        'order_direction' => 'desc',
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

### Field Filtering

Control which fields are audited:

```php
// Exclude specific fields
protected array $auditExclude = ['password', 'remember_token', 'secret_key'];

// Or explicitly include only certain fields
protected array $auditInclude = ['name', 'email', 'role', 'status'];

// You can also implement getAuditExclude() or getAuditInclude() methods 
// for more dynamic control
public function getAuditExclude(): array
{
    $fields = ['password', 'remember_token'];
    
    if (!auth()->user()->isAdmin()) {
        $fields[] = 'admin_notes';
    }
    
    return $fields;
}
```

### Batch Processing

Enable batch processing in your config to improve performance:

```php
// In config/audit-logger.php
'batch' => [
    'enabled' => env('AUDIT_BATCH_ENABLED', true),
    'size' => env('AUDIT_BATCH_SIZE', 50),
    'timeout' => env('AUDIT_BATCH_TIMEOUT', 10), // seconds
],
```

With batching enabled, audit logs are stored in memory and written to the database in batches. You can manually flush the batch:

```php
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;

// Manually flush the batch
AuditLogger::flush();
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

### Using Different Drivers

You can use different drivers for different operations:

```php
// Use MongoDB driver for specific operation
AuditLogger::driver('mongodb')->log(...);

// Use MySQL driver
AuditLogger::driver('mysql')->log(...);
```

### Event-Driven Architecture

The package uses Laravel events. You can listen for audit events:

```php
// In a service provider
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use Illuminate\Support\Facades\Event;

Event::listen(ModelAudited::class, function (ModelAudited $event) {
    // Access event properties
    $entity = $event->entity;
    $action = $event->action;
    $oldValues = $event->oldValues;
    $newValues = $event->newValues;
    
    // Your custom logic
    // For example, send notifications, update cache, etc.
});
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
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_causer (causer_type, causer_id),
    INDEX idx_entity_created (entity_id, created_at),
    INDEX idx_entity_action (entity_id, action)
);
```

### MongoDB

For MongoDB, collections are created with appropriate indexes:

```php
// Collection: audit_products_logs
// Indexes:
// - entity_id: 1
// - action: 1
// - created_at: 1
// - causer_type: 1, causer_id: 1
// - entity_id: 1, created_at: 1
// - entity_id: 1, action: 1
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
   - Model implements `AuditableInterface`
   - Model uses `Auditable` trait
   - Auditing is enabled on the model instance
   - Batch processing might be holding logs (call `AuditLogger::flush()`)
   - Check if queue is running (if using queued audits)

3. **MongoDB driver issues**: Ensure you've installed the MongoDB package:
   ```bash
   composer require mongodb/laravel-mongodb
   ```

### Debugging

Enable debug mode in your Laravel application to see more information about audit logging:

```php
// In .env
APP_DEBUG=true
```

## Performance Considerations

- Use batch processing for high-volume applications
- Consider using queued listeners for audit events (implement `shouldQueueAudit()`)
- Create appropriate indexes if you have custom query patterns
- Regularly archive old audit logs
- For extremely high-volume applications, consider a separate database for audit logs

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.