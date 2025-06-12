# Laravel Audit Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Total Downloads](https://img.shields.io/packagist/dt/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x|11.x|12.x-red.svg?style=flat-square)](https://laravel.com/)
[![GitHub stars](https://img.shields.io/github/stars/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://github.com/iamfarhad/laravel-audit-log/stargazers)

[![License](https://img.shields.io/packagist/l/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-audit-log)
[![Maintained](https://img.shields.io/badge/maintained-yes-brightgreen.svg?style=flat-square)](https://github.com/iamfarhad/laravel-audit-log)
[![Tests](https://img.shields.io/github/actions/workflow/status/iamfarhad/laravel-audit-log/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/iamfarhad/laravel-audit-log/actions/workflows/tests.yml)
[![Code Style](https://img.shields.io/github/actions/workflow/status/iamfarhad/laravel-audit-log/coding-standards.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/iamfarhad/laravel-audit-log/actions/workflows/coding-standards.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg?style=flat-square)](https://github.com/iamfarhad/laravel-audit-log)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://scrutinizer-ci.com/g/iamfarhad/laravel-audit-log)

## Overview

**Laravel Audit Logger** is a powerful and flexible package designed to provide comprehensive audit logging for Laravel applications. It enables tracking of all changes to your Eloquent models with advanced features including source tracking, queue processing, and customizable field management, ensuring compliance with regulatory requirements, aiding in debugging, and maintaining data integrity. Built with modern PHP and Laravel practices, this package adheres to strict typing, PSR-12 coding standards, and leverages dependency injection for maximum testability and maintainability.

The package uses a high-performance direct logging architecture with optional queue integration, making it suitable for both small applications and enterprise-scale systems.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Basic Usage](#basic-usage)
  - [Advanced Usage](#advanced-usage)
  - [Source Tracking](#source-tracking)
  - [Queue Processing](#queue-processing)
- [Customizing Audit Logging](#customizing-audit-logging)
- [Retrieving Audit Logs](#retrieving-audit-logs)
- [Performance Optimization](#performance-optimization)
- [Testing](#testing)
- [Security Best Practices](#security-best-practices)
- [Migration & Upgrade Guide](#migration--upgrade-guide)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Entity-Specific Audit Tables**: Automatically creates dedicated tables for each audited model to optimize performance and querying.
- **Comprehensive Change Tracking**: Logs all CRUD operations (create, update, delete, restore) with old and new values.
- **Advanced Source Tracking**: Automatically tracks the source of changes (console commands, HTTP routes, background jobs) for enhanced debugging and compliance.
- **Queue Processing**: Supports both synchronous and asynchronous audit log processing for improved performance.
- **Customizable Field Management**: Control which fields to include or exclude from auditing with global and model-specific configurations.
- **User Tracking**: Automatically identifies and logs the user (causer) responsible for changes with configurable guard and resolver support.
- **Direct Logging Architecture**: Uses direct service calls for high-performance logging with optional event integration.
- **Batch Processing**: Supports batch operations for high-performance logging in large-scale applications.
- **Type Safety**: Built with PHP 8.1+ strict typing, readonly properties, and modern features.
- **Enhanced Query Scopes**: Comprehensive filtering capabilities with dedicated scopes for different query patterns.
- **Extensible Drivers**: Supports multiple storage drivers (currently MySQL) with the ability to implement custom drivers.
- **Automatic Migration**: Seamlessly creates audit tables for new models when enabled.
- **Static Analysis**: Level 5 PHPStan compliance for maximum code quality and reliability.

## Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.x, 11.x, or 12.x
- **Database**: MySQL 8.0+ (for the default driver)

## Installation

Install the package via Composer:

```bash
composer require iamfarhad/laravel-audit-log
```

After installation, publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=audit-logger-config
```

This will create a configuration file at `config/audit-logger.php` where you can adjust settings like the storage driver, table naming conventions, queue processing, and more.

## Configuration

The configuration file `config/audit-logger.php` allows you to customize the behavior of the audit logger. Below are the key configuration options:

```php
return [
    // Default audit driver
    'default' => env('AUDIT_DRIVER', 'mysql'),

    // Driver-specific configurations
    'drivers' => [
        'mysql' => [
            'connection' => env('AUDIT_MYSQL_CONNECTION', config('database.default')),
            'table_prefix' => env('AUDIT_TABLE_PREFIX', 'audit_'),
            'table_suffix' => env('AUDIT_TABLE_SUFFIX', '_logs'),
        ],
    ],

    // Queue processing configuration
    'queue' => [
        'enabled' => env('AUDIT_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_QUEUE_CONNECTION', config('queue.default')),
        'queue_name' => env('AUDIT_QUEUE_NAME', 'audit'),
        'delay' => env('AUDIT_QUEUE_DELAY', 0),
    ],

    // Enable automatic migration for audit tables
    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

    // Enhanced global field exclusions and configuration
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

    // Enhanced causer identification settings
    'causer' => [
        'guard' => null, // null means use default guard
        'model' => null, // null means auto-detect
        'resolver' => null, // custom resolver class
    ],

    // Registered entities for centralized configuration
    'entities' => [
        // Example entity configuration:
        // \App\Models\User::class => [
        //     'table' => 'users',
        //     'exclude' => ['password'],
        //     'include' => ['*'],
        // ],
    ],
];
```

### Environment Variables

You can use environment variables to configure the audit logger:

```bash
# Driver Configuration
AUDIT_DRIVER=mysql
AUDIT_MYSQL_CONNECTION=mysql
AUDIT_TABLE_PREFIX=audit_
AUDIT_TABLE_SUFFIX=_logs

# Queue Configuration
AUDIT_QUEUE_ENABLED=false
AUDIT_QUEUE_CONNECTION=redis
AUDIT_QUEUE_NAME=audit
AUDIT_QUEUE_DELAY=0

# Migration Configuration
AUDIT_AUTO_MIGRATION=true
```

### Database Schema

The audit logger creates tables with the following structure for each audited model:

```sql
CREATE TABLE audit_{model_name}_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id VARCHAR(255) NOT NULL,
    action VARCHAR(255) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    causer_type VARCHAR(255) NULL,
    causer_id VARCHAR(255) NULL,
    metadata JSON NULL,
    source VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL,
    
    INDEX idx_entity_id (entity_id),
    INDEX idx_causer (causer_type, causer_id),
    INDEX idx_created_at (created_at),
    INDEX idx_source (source),
    INDEX idx_action (action)
);
```

The `source` field is automatically populated to track the origin of changes:
- Console commands: Command name (e.g., `app:send-emails`)
- HTTP requests: Controller action (e.g., `App\Http\Controllers\UserController@update`)
- Background jobs: Job class name
- Queue workers: Queue job processing context

## Usage

### Basic Usage

To make a model auditable, simply add the `Auditable` trait to your Eloquent model. Ensure strict typing is enabled as per the engineering rules.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

final class Order extends Model
{
    use Auditable;

    protected $fillable = ['customer_id', 'total', 'status'];
}
```

Once the trait is added, any changes to the model (create, update, delete, restore) will be automatically logged to a dedicated audit table (e.g., `audit_orders_logs`).

#### Excluding Fields

To exclude specific fields from being audited, define the `$auditExclude` property:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

final class User extends Model
{
    use Auditable;

    protected array $auditExclude = [
        'password',
        'remember_token',
        'email_verified_at',
    ];
}
```

#### Including Specific Fields

Alternatively, you can specify only the fields to audit using `$auditInclude`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

final class Invoice extends Model
{
    use Auditable;

    protected array $auditInclude = [
        'amount',
        'status',
        'due_date',
    ];
}
```

### Advanced Usage

#### Custom Metadata

You can enrich audit logs with custom metadata by implementing the `getAuditMetadata` method in your model:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

final class Transaction extends Model
{
    use Auditable;

    public function getAuditMetadata(): array
    {
        return [
            'ip_address' => request()->ip() ?? 'unknown',
            'user_agent' => request()->userAgent() ?? 'unknown',
            'request_id' => request()->header('X-Request-Id', 'n/a'),
            'session_id' => session()->getId() ?? null,
        ];
    }
}
```

#### Source Tracking

The audit logger automatically tracks the source of changes to help with debugging and compliance. The source field captures:

- **Console Commands**: Artisan command names (e.g., `app:send-emails`, `migrate`, `queue:work`)
- **HTTP Routes**: Controller action names or route names for web requests
- **Background Jobs**: Job class names when changes occur within queued jobs
- **Queue Workers**: Processing context for queue-based operations

```php
// Example audit log entries with source tracking:

// From console command: php artisan app:send-emails
[
    'entity_id' => '1',
    'action' => 'updated',
    'old_values' => '{"email": "old@example.com"}',
    'new_values' => '{"email": "new@example.com"}',
    'source' => 'app:send-emails',
    'created_at' => '2024-01-15 10:30:00'
]

// From HTTP request
[
    'entity_id' => '1', 
    'action' => 'updated',
    'old_values' => '{"status": "pending"}',
    'new_values' => '{"status": "approved"}',
    'source' => 'App\\Http\\Controllers\\OrderController@approve',
    'created_at' => '2024-01-15 10:35:00'
]

// From background job
[
    'entity_id' => '1',
    'action' => 'updated',
    'old_values' => '{"processed": false}',
    'new_values' => '{"processed": true}',
    'source' => 'App\\Jobs\\ProcessPayment',
    'created_at' => '2024-01-15 10:40:00'
]
```

#### Enhanced Query Scopes for Source Filtering

Query audit logs by source using convenient scopes:

```php
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

// Find all changes made by a specific console command
$commandLogs = EloquentAuditLog::forEntity(User::class)
    ->fromCommand('app:send-emails')
    ->get();

// Find all changes made through console commands
$consoleLogs = EloquentAuditLog::forEntity(User::class)
    ->fromConsole()
    ->get();

// Find all changes made through HTTP requests
$webLogs = EloquentAuditLog::forEntity(User::class)
    ->fromHttp()
    ->get();

// Find changes from a specific controller
$controllerLogs = EloquentAuditLog::forEntity(User::class)
    ->fromController('UserController')
    ->get();

// Find changes by exact source match
$exactSourceLogs = EloquentAuditLog::forEntity(User::class)
    ->forSource('app:send-emails')
    ->get();

// Complex filtering with multiple scopes
$filteredLogs = EloquentAuditLog::forEntity(User::class)
    ->fromConsole()
    ->forAction('updated')
    ->dateBetween(now()->subWeek(), now())
    ->forCauserId(1)
    ->orderBy('created_at', 'desc')
    ->paginate(20);
```

#### Temporarily Disabling Auditing

For specific operations where auditing is not required, you can disable it temporarily:

```php
$user = User::find(1);
$user->disableAuditing();
$user->update(['email' => 'new.email@example.com']); // This change won't be logged
$user->enableAuditing();
```

#### Custom Audit Events with Fluent API

To log custom actions beyond standard CRUD operations, use the fluent API provided by the `audit()` method:

```php
<?php

declare(strict_types=1);

use App\Models\Order;

$order = Order::find(1);
$order->audit()
    ->custom('status_transition')
    ->from(['status' => 'pending', 'previous_state' => 'draft'])
    ->to(['status' => 'shipped', 'tracking_number' => 'TRK123456'])
    ->withMetadata([
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'reason' => 'Automatic shipment processing',
        'batch_id' => 'BATCH_001'
    ])
    ->log();
```

This fluent interface provides:
- Better performance than event-driven architectures
- Direct database logging without event dispatch overhead
- Respects model's auditable attributes
- Merges default metadata from `getAuditMetadata()` with custom metadata
- Maintains source tracking for custom events

### Queue Processing

The audit logger supports both synchronous and asynchronous processing of audit logs for improved performance in high-traffic applications.

#### Enabling Queue Processing

Update your configuration to enable queue processing:

```php
// config/audit-logger.php
'queue' => [
    'enabled' => true,
    'connection' => 'redis', // or your preferred queue connection
    'queue_name' => 'audit',
    'delay' => 0, // delay in seconds before processing
],
```

Or use environment variables:

```bash
AUDIT_QUEUE_ENABLED=true
AUDIT_QUEUE_CONNECTION=redis
AUDIT_QUEUE_NAME=audit
AUDIT_QUEUE_DELAY=0
```

#### Queue Jobs Architecture

The package includes two specialized queue jobs:

1. **`ProcessAuditLogJob`**: Handles standard asynchronous audit log processing
2. **`ProcessAuditLogSyncJob`**: Provides fallback synchronous processing when needed

#### Queue Configuration Options

- **`enabled`**: Boolean to enable/disable queue processing (default: `false`)
- **`connection`**: Queue connection to use (default: your app's default queue connection)
- **`queue_name`**: Name of the queue to dispatch audit jobs to (default: `audit`)
- **`delay`**: Delay in seconds before processing the job (default: `0` for immediate processing)

#### Benefits of Queue Processing

1. **Improved Performance**: Audit logging doesn't block your application's main thread
2. **Scalability**: Handle high-volume audit logging without impacting user experience
3. **Reliability**: Failed audit logs can be retried automatically with Laravel's queue system
4. **Resource Management**: Control when and how audit logs are processed
5. **Better Error Handling**: Queue-specific error handling and monitoring

#### Queue Worker Setup

When using queue processing, ensure you have queue workers running:

```bash
# Start a queue worker specifically for the audit queue
php artisan queue:work --queue=audit

# Or run workers for all queues
php artisan queue:work

# For production with process management
php artisan queue:work --queue=audit --tries=3 --timeout=60
```

For production environments, consider using Supervisor or similar process managers to keep your queue workers running reliably.

## Customizing Audit Logging

### Custom Driver Implementation

If you need to extend the audit logging functionality, you can implement a custom driver by adhering to the `AuditDriverInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Audit\Drivers;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;

final class CustomAuditDriver implements AuditDriverInterface
{
    public function log(AuditLog $auditLog): void
    {
        // Your custom logging implementation
    }

    public function createStorageForEntity(string $entityClass): void
    {
        // Your custom storage creation logic
    }

    // ... implement other required methods
}
```

Register your custom driver in a service provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use App\Audit\Drivers\CustomAuditDriver;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditDriverInterface::class, CustomAuditDriver::class);
    }
}
```

### Custom Causer Resolver

You can implement a custom causer resolver for complex authentication scenarios:

```php
<?php

declare(strict_types=1);

namespace App\Audit;

use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class CustomCauserResolver implements CauserResolverInterface
{
    public function resolve(): ?Model
    {
        // Your custom causer resolution logic
        return auth()->user() ?? $this->getSystemUser();
    }

    private function getSystemUser(): ?Model
    {
        // Return a system user for automated processes
        return User::where('email', 'system@example.com')->first();
    }
}
```

## Retrieving Audit Logs

### Model Relationship

Audit logs are accessible via a relationship on the audited model:

```php
$user = User::find(1);

// Retrieve all audit logs
$allLogs = $user->auditLogs()->get();

// Filter by specific action
$updateLogs = $user->auditLogs()->where('action', 'updated')->get();

// Get the most recent logs with pagination
$recentLogs = $user->auditLogs()
    ->orderBy('created_at', 'desc')
    ->paginate(10);

// Include metadata and causer information
$detailedLogs = $user->auditLogs()
    ->with(['causer'])
    ->orderBy('created_at', 'desc')
    ->get();
```

### Advanced Querying with EloquentAuditLog

For more complex queries, use the `EloquentAuditLog` model directly with comprehensive scopes:

```php
<?php

declare(strict_types=1);

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

// Basic entity filtering
$logs = EloquentAuditLog::forEntity(User::class)
    ->forEntityId(1)
    ->orderBy('created_at', 'desc')
    ->get();

// Complex multi-criteria filtering
$filteredLogs = EloquentAuditLog::forEntity(Order::class)
    ->forAction('updated')
    ->forCauserId(1)
    ->fromCommand('app:process-orders')
    ->dateBetween(now()->subWeek(), now())
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Source-specific queries
$consoleLogs = EloquentAuditLog::forEntity(User::class)
    ->fromConsole()
    ->dateGreaterThan(now()->subHour())
    ->get();

$controllerLogs = EloquentAuditLog::forEntity(Order::class)
    ->fromController('OrderController')
    ->forAction(['created', 'updated'])
    ->get();

// Aggregation and analytics
$dailyStats = EloquentAuditLog::forEntity(User::class)
    ->selectRaw('DATE(created_at) as date, action, COUNT(*) as count')
    ->dateBetween(now()->subMonth(), now())
    ->groupBy('date', 'action')
    ->orderBy('date', 'desc')
    ->get();
```

### Available Query Scopes

The `EloquentAuditLog` model provides comprehensive scopes for efficient filtering:

#### Entity and Basic Filtering
- `forEntity(string $entityClass)` - Filter by entity type
- `forEntityId($entityId)` - Filter by entity ID
- `forAction(string|array $action)` - Filter by action(s)
- `forCauser(string $causerClass)` - Filter by causer type
- `forCauserId($causerId)` - Filter by causer ID

#### Date Filtering
- `forCreatedAt($createdAt)` - Filter by exact creation date
- `dateGreaterThan($date)` - Filter for logs after a specific date
- `dateLessThan($date)` - Filter for logs before a specific date
- `dateBetween($startDate, $endDate)` - Filter for logs within a date range

#### Enhanced Source Filtering
- `forSource(string $source)` - Filter by exact source match
- `fromConsole()` - Filter for logs from console commands
- `fromHttp()` - Filter for logs from HTTP requests
- `fromCommand(string $command)` - Filter by specific console command
- `fromController(?string $controller = null)` - Filter by controller

## Performance Optimization

### Database Optimization
- **Automatic Indexing**: The package automatically creates indexes on frequently queried columns (`entity_id`, `causer_type`, `causer_id`, `created_at`, `source`, `action`)
- **Entity-Specific Tables**: Each model gets its own audit table for optimal query performance
- **JSON Column Usage**: Efficiently stores old/new values and metadata using MySQL's native JSON support

### Application-Level Optimization
- **Queue Processing**: Enable queue processing for high-traffic applications to improve response times
- **Selective Auditing**: Limit audited fields to only those necessary for compliance or debugging
- **Batch Operations**: Use the audit service's batch capabilities for bulk operations
- **Strategic Field Exclusion**: Configure global and model-specific field exclusions to reduce storage overhead

### Recommended Configuration for High-Traffic Applications

```php
// config/audit-logger.php
return [
'queue' => [
    'enabled' => true,
        'connection' => 'redis',
    'queue_name' => 'audit',
    ],
    'fields' => [
        'exclude' => [
            // Add non-critical fields to reduce storage
            'updated_at',
            'last_activity',
            'view_count',
        ],
        'include_timestamps' => false, // Disable if not needed
    ],
];
```

### Monitoring and Maintenance

```php
// Example service for audit log maintenance
<?php

declare(strict_types=1);

namespace App\Services;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

final class AuditMaintenanceService
{
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        return EloquentAuditLog::where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    public function getStorageStats(): array
    {
        return [
            'total_logs' => EloquentAuditLog::count(),
            'logs_last_30_days' => EloquentAuditLog::dateGreaterThan(now()->subDays(30))->count(),
            'most_active_entities' => EloquentAuditLog::selectRaw('entity_type, COUNT(*) as count')
                ->groupBy('entity_type')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}
```

## Testing

This package includes a comprehensive test suite with high coverage. To run the tests locally:

```bash
# Run the full test suite
composer test

# Run with coverage
composer test:coverage

# Run specific test suites
vendor/bin/phpunit tests/Unit/
vendor/bin/phpunit tests/Feature/
```

### Test Categories

The test suite includes:

- **Unit Tests**: Test individual components in isolation
  - `AuditLoggerServiceTest`: Service layer testing
  - `MySQLDriverTest`: Driver functionality
  - `CauserResolverTest`: User identification
  - `AuditableTraitTest`: Trait functionality

- **Feature Tests**: Test integration between components
  - `AuditLogIntegrationTest`: Full integration scenarios
  - `AuditLogBatchTest`: Batch processing
  - `CustomAuditActionTest`: Custom audit events

- **Integration Tests**: Test real-world scenarios with database

### Testing Your Implementation

When writing tests for your application, ensure you cover audit logging behavior:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

final class UserAuditTest extends TestCase
{
    public function test_user_creation_is_audited(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'created',
        ]);

        $auditLog = EloquentAuditLog::forEntity(User::class)
            ->forEntityId($user->id)
            ->forAction('created')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('created', $auditLog->action);
        $this->assertNotNull($auditLog->new_values);
    }

    public function test_sensitive_fields_are_excluded(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('secret'),
        ]);

        $auditLog = EloquentAuditLog::forEntity(User::class)
            ->forEntityId($user->id)
            ->first();

        $newValues = json_decode($auditLog->new_values, true);
        $this->assertArrayNotHasKey('password', $newValues);
    }
}
```

## Security Best Practices

### Data Protection
- **Exclude Sensitive Data**: Always exclude fields containing PII or sensitive data using `$auditExclude` or global configuration
- **Enhanced Security Fields**: The package now excludes common sensitive fields by default (API keys, tokens, payment information)
- **Metadata Sanitization**: Be cautious about what data you include in custom metadata

### Access Control
- **Authorization Gates**: Implement Laravel Gates or Policies to restrict access to audit logs
- **Role-Based Access**: Consider different access levels for different types of audit data
- **API Protection**: If exposing audit logs via API, ensure proper authentication and rate limiting

### Data Retention and Compliance
- **Retention Policies**: Implement automated cleanup of old audit logs based on your compliance requirements
- **GDPR Compliance**: Consider implementing user data deletion when users request account deletion
- **Export Capabilities**: Provide audit trail export functionality for compliance reporting

```php
// Example policy for audit log access
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit.view');
    }

    public function view(User $user, string $entityType, $entityId): bool
    {
        return $user->hasPermission('audit.view') 
            && $this->canAccessEntity($user, $entityType, $entityId);
    }

    private function canAccessEntity(User $user, string $entityType, $entityId): bool
    {
        // Implement your business logic for entity access
        return true;
    }
}
```

## Migration & Upgrade Guide

### Upgrading from Version 1.2.x to 1.3.x

Version 1.3.0 introduced breaking changes with the move from event-driven to direct logging architecture:

#### Breaking Changes
- **Event System Removed**: `ModelAudited` event and `AuditModelChanges` listener have been removed
- **Direct Logging**: The system now uses direct service calls instead of event dispatching
- **Enhanced Configuration**: New configuration options for causer resolution and entity management

#### Migration Steps

1. **Update Configuration**: Publish the new configuration file:
   ```bash
   php artisan vendor:publish --tag=audit-logger-config --force
   ```

2. **Database Schema**: Add the `source` column to existing audit tables:
  ```sql
  ALTER TABLE audit_your_model_logs ADD COLUMN source VARCHAR(255) NULL;
  CREATE INDEX idx_source ON audit_your_model_logs (source);
  ```

3. **Remove Event Listeners**: If you were listening to `ModelAudited` events, replace with direct service usage or custom implementations.

4. **Test Your Implementation**: Ensure all audit logging continues to work as expected.

### Upgrading from Version 1.1.x to 1.2.x

Version 1.2.0 introduced structural changes:

- **DTO Introduction**: `AuditLog` moved from Models to DTOs namespace
- **Enhanced EloquentAuditLog**: New model with comprehensive scopes
- **Improved Test Suite**: Better test coverage and organization

Most changes are backward compatible, but you should update any direct references to the old `AuditLog` model class.

## Troubleshooting

### Common Issues and Solutions

#### Audit Tables Not Created
- **Solution**: Ensure `'auto_migration' => true` in your configuration
- **Manual Creation**: Use `AuditLogger::driver()->createStorageForEntity(Model::class)`

#### Missing Logs
- **Check Field Exclusion**: Verify fields aren't excluded globally or in the model
- **Auditing Status**: Ensure auditing isn't disabled for the operation
- **Queue Processing**: If using queues, ensure workers are running

#### Causer Not Recorded
- **Authentication**: Confirm user is logged in during the operation
- **Guard Configuration**: Check the causer guard configuration
- **Custom Resolver**: Verify custom causer resolver implementation

#### Source Field Issues
- **Console Commands**: Ensure `$_SERVER['argv']` is available
- **HTTP Requests**: Verify route is properly registered
- **Context Detection**: Check application is running in expected context

#### Queue Processing Issues
- **Worker Status**: Ensure queue workers are running: `php artisan queue:work --queue=audit`
- **Connection**: Verify queue connection is properly configured
- **Failed Jobs**: Monitor with `php artisan queue:failed`
- **Debugging**: Temporarily disable queues to test synchronous processing

#### Performance Issues
- **Enable Queues**: For high-traffic applications, enable queue processing
- **Field Exclusion**: Exclude non-essential fields to reduce storage overhead
- **Database Optimization**: Ensure proper indexing (handled automatically)
- **Batch Operations**: Use batch processing for bulk operations

#### Static Analysis Errors
- **PHPStan**: The package is PHPStan Level 5 compliant
- **Dynamic Methods**: Some warnings about dynamic method calls are expected and ignored
- **Type Safety**: Ensure strict typing is enabled in your models

### Debug Mode

Enable debug logging to troubleshoot issues:

```php
// In your service provider or configuration
use iamfarhad\LaravelAuditLog\Services\AuditLogger;

// Enable debug mode (not recommended for production)
AuditLogger::enableDebugMode();

// This will log additional information about audit processing
```

### Getting Help

1. **Check Documentation**: Review this README and the changelog
2. **Search Issues**: Look through existing GitHub issues
3. **Create Issue**: If you find a bug, create a detailed issue with:
   - Laravel version
   - PHP version
   - Package version
   - Minimal reproduction steps
   - Expected vs actual behavior

## Contributing

Contributions are welcome! Please follow these guidelines:

### Development Setup

1. **Fork the Repository**: Create your own fork of the project
2. **Clone Locally**: `git clone your-fork-url`
3. **Install Dependencies**: `composer install`
4. **Run Tests**: `composer test` to ensure everything works

### Code Standards

- **PSR-12**: Follow PSR-12 coding standards
- **Strict Types**: Use `declare(strict_types=1);` in all PHP files
- **Type Hints**: Provide type hints for all parameters and return values
- **PHPStan**: Code must pass PHPStan Level 5 analysis
- **Tests**: Include tests for new features and bug fixes

### Pull Request Process

1. **Create Branch**: Create a feature branch from `main`
2. **Write Code**: Implement your changes following the code standards
3. **Add Tests**: Include comprehensive tests for your changes
4. **Update Documentation**: Update README and changelog as needed
5. **Run Quality Checks**: 
   ```bash
   composer test
   composer pint
   composer phpstan
   ```
6. **Submit PR**: Create a detailed pull request with description of changes

### Code Quality Tools

The project uses several quality assurance tools:

```bash
# Run all tests
composer test

# Fix code style
composer pint

# Run static analysis
composer phpstan

# Check test coverage
composer test:coverage
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

---

**Laravel Audit Logger** - Comprehensive audit logging for modern Laravel applications with advanced source tracking, queue processing, and enterprise-grade features.