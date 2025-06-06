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
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://github.com/iamfarhad/laravel-audit-log)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/iamfarhad/laravel-audit-log.svg?style=flat-square)](https://scrutinizer-ci.com/g/iamfarhad/laravel-audit-log)

## Overview

**Laravel Audit Logger** is a powerful and flexible package designed to provide detailed audit logging for Laravel applications. It enables tracking of all changes to your Eloquent models with comprehensive source tracking, ensuring compliance with regulatory requirements, aiding in debugging, and maintaining data integrity. Built with modern PHP and Laravel practices, this package adheres to strict typing, PSR-12 coding standards, and leverages dependency injection for maximum testability and maintainability.

The package uses a high-performance direct logging architecture while maintaining flexibility through optional event integration, making it suitable for both small applications and enterprise-scale systems.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Basic Usage](#basic-usage)
  - [Advanced Usage](#advanced-usage)
  - [Source Tracking](#source-tracking)
- [Customizing Audit Logging](#customizing-audit-logging)
- [Retrieving Audit Logs](#retrieving-audit-logs)
- [Performance Optimization](#performance-optimization)
- [Testing](#testing)
- [Security Best Practices](#security-best-practices)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Entity-Specific Audit Tables**: Automatically creates dedicated tables for each audited model to optimize performance and querying.
- **Comprehensive Change Tracking**: Logs all CRUD operations (create, update, delete, restore) with old and new values.
- **Source Tracking**: Automatically tracks the source of changes (console commands, HTTP routes, etc.) for enhanced debugging and compliance.
- **Customizable Field Logging**: Control which fields to include or exclude from auditing.
- **User Tracking**: Automatically identifies and logs the user (causer) responsible for changes.
- **Direct Logging Architecture**: Uses direct service calls for high-performance, synchronous audit logging with optional event integration.
- **Batch Processing**: Supports batch operations for high-performance logging in large-scale applications.
- **Type Safety**: Built with PHP 8.1+ strict typing and modern features like `readonly` properties and enums.
- **Extensible Drivers**: Supports multiple storage drivers (currently MySQL) with the ability to implement custom drivers.
- **Automatic Migration**: Seamlessly creates audit tables for new models when enabled.

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

This will create a configuration file at `config/audit-logger.php` where you can adjust settings like the storage driver, table naming conventions, and more.

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

    // Enable automatic migration for audit tables
    'auto_migration' => env('AUDIT_AUTO_MIGRATION', true),

    // Global field exclusions
    'fields' => [
        'exclude' => [
            'password',
            'remember_token',
            'api_token',
        ],
        'include_timestamps' => false,
    ],

    // Batch processing settings for performance
    'batch' => [
        'enabled' => env('AUDIT_BATCH_ENABLED', false),
        'size' => env('AUDIT_BATCH_SIZE', 100),
    ],

    // Causer (user) identification settings
    'causer' => [
        'guard' => null, // Use default auth guard if null
        'resolver' => null, // Custom causer resolver class
    ],
];
```

Ensure you review and adjust these settings based on your application's needs, especially for sensitive data exclusion and performance optimization.

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
    created_at TIMESTAMP NOT NULL,
    source VARCHAR(255) NULL,
    
    INDEX idx_entity_id (entity_id),
    INDEX idx_causer (causer_type, causer_id),
    INDEX idx_created_at (created_at),
    INDEX idx_source (source)
);
```

The `source` field is automatically populated to track the origin of changes:
- Console commands: Command name (e.g., `app:send-emails`)
- HTTP requests: Controller action (e.g., `App\Http\Controllers\UserController@update`)
- Background jobs: Job class name

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

Once the trait is added, any changes to the model (create, update, delete, restore) will be automatically logged to a dedicated audit table (e.g., `audit_order_logs`).

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
        ];
    }
}
```

#### Source Tracking

The audit logger automatically tracks the source of changes to help with debugging and compliance. The source field captures:

- **Console Commands**: Artisan command names (e.g., `app:send-emails`, `migrate`, `queue:work`)
- **HTTP Routes**: Controller action names or route names for web requests
- **Background Jobs**: Job class names when changes occur within queued jobs

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
```

You can query audit logs by source using convenient scopes:

```php
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

// Using dedicated source scopes for cleaner queries

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

// Combine with other scopes
$filteredLogs = EloquentAuditLog::forEntity(User::class)
    ->fromConsole()
    ->forAction('updated')
    ->dateBetween(now()->subWeek(), now())
    ->orderBy('created_at', 'desc')
    ->get();
```

#### Temporarily Disabling Auditing

For specific operations where auditing is not required, you can disable it temporarily:

```php
$user = User::find(1);
$user->disableAuditing();
$user->update(['email' => 'new.email@example.com']); // This change won't be logged
$user->enableAuditing();
```

#### Custom Audit Events

To log custom actions beyond standard CRUD operations, use the fluent API provided by the `audit()` method for direct, high-performance logging:

```php
<?php

declare(strict_types=1);

use App\Models\Order;

$order = Order::find(1);
$order->audit()
    ->custom('status_changed')
    ->from(['status' => 'pending'])
    ->to(['status' => 'shipped'])
    ->withMetadata(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
    ->log();
```

This approach provides better performance than event-driven architectures since it logs directly to the database without the overhead of event dispatching and listening.

#### Fluent API for Custom Audit Events

For a more intuitive and readable way to log custom actions, use the fluent API provided by the `audit()` method:

```php
<?php

declare(strict_types=1);

use App\Models\Order;

$order = Order::find(1);
$order->audit()
    ->custom('status_transition')
    ->from(['status' => 'pending'])
    ->to(['status' => 'shipped'])
    ->withMetadata(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
    ->log();
```

This fluent interface allows you to chain methods to define the custom action, old and new values, and additional metadata before logging the event. It respects the model's auditable attributes and merges any default metadata defined in `getAuditMetadata()` with custom metadata provided.

## Customizing Audit Logging

If you need to extend the audit logging functionality, you can implement a custom driver by adhering to the `AuditDriverInterface`. Register your custom driver in a service provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use App\Audit\CustomAuditDriver;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditDriverInterface::class, CustomAuditDriver::class);
    }
}
```

## Retrieving Audit Logs

Audit logs are accessible via a relationship on the audited model. Use the `auditLogs()` method to query logs:

```php
$user = User::find(1);

// Retrieve all audit logs
$allLogs = $user->auditLogs()->get();

// Filter by specific action
$updateLogs = $user->auditLogs()->where('action', 'updated')->get();

// Get the most recent logs
$recentLogs = $user->auditLogs()->orderBy('created_at', 'desc')->take(5)->get();
```

For advanced querying, you can directly use the `EloquentAuditLog` model with various scopes to filter audit logs based on specific criteria:

```php
<?php

declare(strict_types=1);

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

// Get logs for a specific entity type and ID
$logs = EloquentAuditLog::forEntity(User::class)
    ->where('entity_id', 1)
    ->where('action', 'updated')
    ->where('created_at', '>=', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();

// Use scope to filter by action type
$createdLogs = EloquentAuditLog::forEntity(User::class)
    ->forAction('created')
    ->where('entity_id', 1)
    ->get();

// Use scope to filter by date range
$lastMonthLogs = EloquentAuditLog::forEntity(User::class)
    ->dateBetween(now()->subDays(30), now())
    ->where('entity_id', 1)
    ->orderBy('created_at', 'desc')
    ->get();

// Use scope to filter by causer (user who performed the action)
$adminLogs = EloquentAuditLog::forEntity(User::class)
    ->forCauserId(1) // Assuming causer_id 1 is the admin
    ->where('entity_id', 1)
    ->get();

// Filter by source using dedicated scopes
$consoleLogs = EloquentAuditLog::forEntity(User::class)
    ->fromCommand('app:send-emails')
    ->where('entity_id', 1)
    ->get();

$webLogs = EloquentAuditLog::forEntity(User::class)
    ->fromHttp()
    ->where('entity_id', 1)
    ->get();

$controllerLogs = EloquentAuditLog::forEntity(User::class)
    ->fromController('UserController')
    ->where('entity_id', 1)
    ->get();

// Combine multiple scopes for precise filtering
$filteredLogs = EloquentAuditLog::forEntity(User::class)
    ->forAction('updated')
    ->forCauserId(1)
    ->fromCommand('app:send-emails')
    ->dateBetween(now()->subDays(7), now())
    ->where('entity_id', 1)
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get();
```

These scopes allow for flexible and efficient querying of audit logs, making it easier to analyze changes based on action type, date range, or the user responsible for the change.

### Available Query Scopes

The `EloquentAuditLog` model provides several convenient scopes for filtering audit logs:

#### Entity and Basic Filtering
- `forEntity(string $entityClass)` - Filter by entity type (e.g., `User::class`)
- `forEntityId($entityId)` - Filter by entity ID
- `forAction(string $action)` - Filter by action (`created`, `updated`, `deleted`, etc.)
- `forCauser(string $causerClass)` - Filter by causer type
- `forCauserId($causerId)` - Filter by causer ID

#### Date Filtering
- `forCreatedAt($createdAt)` - Filter by exact creation date
- `dateGreaterThan($date)` - Filter for logs after a specific date
- `dateLessThan($date)` - Filter for logs before a specific date
- `dateBetween($startDate, $endDate)` - Filter for logs within a date range

#### Source Filtering
- `forSource(string $source)` - Filter by exact source match
- `fromConsole()` - Filter for logs from console commands
- `fromHttp()` - Filter for logs from HTTP requests
- `fromCommand(string $command)` - Filter by specific console command
- `fromController(string $controller = null)` - Filter by controller (or all controllers if no parameter)

```php
// Examples of scope combinations
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

// Complex filtering example
$logs = EloquentAuditLog::forEntity(User::class)
    ->forAction('updated')
    ->fromConsole()
    ->dateBetween(now()->subWeek(), now())
    ->forCauserId(1)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Find all user updates from a specific command in the last 24 hours
$recentCommandLogs = EloquentAuditLog::forEntity(User::class)
    ->forAction('updated')
    ->fromCommand('app:sync-users')
    ->dateGreaterThan(now()->subDay())
    ->get();
```

## Performance Optimization

- **Batch Processing**: Enable batch processing in the configuration to reduce database transactions for high-frequency operations.
  ```php
  'batch' => [
      'enabled' => true,
      'size' => 100,
  ],
  ```
- **Selective Auditing**: Limit audited fields to only those necessary for compliance or debugging to minimize storage and processing overhead.
- **Database Indexing**: Ensure audit tables have appropriate indexes for frequent queries (handled automatically by the package).

## Testing

This package includes a robust test suite. To run the tests locally:

```bash
composer test
```

When writing tests for your application, ensure you cover audit logging behavior, especially for critical models. Mock the audit driver if necessary to isolate tests from actual logging.

## Security Best Practices

- **Exclude Sensitive Data**: Always exclude fields containing personally identifiable information (PII) or sensitive data using `$auditExclude`.
- **Access Control**: Implement authorization checks (using Laravel Gates or Policies) to restrict access to audit logs.
- **Data Retention**: Consider implementing a retention policy to purge old audit logs, balancing compliance needs with data privacy.

## Troubleshooting

- **Audit Tables Not Created**: Ensure `'auto_migration' => true` in your configuration. If disabled, manually create tables using `AuditLogger::driver()->createStorageForEntity(Model::class)`.
- **Missing Logs**: Verify that fields aren't excluded globally or in the model, and ensure auditing isn't disabled for the operation.
- **Causer Not Recorded**: Confirm that authentication is set up correctly and the user is logged in during the operation.
- **Source Field Empty**: The source field should automatically populate. If it's null, check that:
  - For console commands: `$_SERVER['argv']` is available and contains the command name
  - For HTTP requests: The route is properly registered and accessible via `Request::route()`
  - The application is running in the expected context (console vs. HTTP)
- **Migration Issues**: If upgrading from a previous version, ensure your existing audit tables include the `source` column. You may need to add it manually:
  ```sql
  ALTER TABLE audit_your_model_logs ADD COLUMN source VARCHAR(255) NULL;
  CREATE INDEX idx_source ON audit_your_model_logs (source);
  ```

## Contributing

Contributions are welcome! Please follow the guidelines in [CONTRIBUTING.md](CONTRIBUTING.md) for submitting pull requests, reporting issues, or suggesting features.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).