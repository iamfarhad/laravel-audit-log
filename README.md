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

## Overview

**Laravel Audit Logger** is a powerful and flexible package designed to provide detailed audit logging for Laravel applications. It enables tracking of all changes to your Eloquent models, ensuring compliance with regulatory requirements, aiding in debugging, and maintaining data integrity. Built with modern PHP and Laravel practices, this package adheres to strict typing, PSR-12 coding standards, and leverages dependency injection for maximum testability and maintainability.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Basic Usage](#basic-usage)
  - [Advanced Usage](#advanced-usage)
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
- **Customizable Field Logging**: Control which fields to include or exclude from auditing.
- **User Tracking**: Automatically identifies and logs the user (causer) responsible for changes.
- **Event-Driven Architecture**: Utilizes Laravel Events for decoupled and extensible audit logging.
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

#### Temporarily Disabling Auditing

For specific operations where auditing is not required, you can disable it temporarily:

```php
$user = User::find(1);
$user->disableAuditing();
$user->update(['email' => 'new.email@example.com']); // This change won't be logged
$user->enableAuditing();
```

#### Custom Audit Events

To log custom actions beyond standard CRUD operations, dispatch the `ModelAudited` event manually:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;

$order = Order::find(1);
Event::dispatch(new ModelAudited(
    model: $order,
    action: 'status_changed',
    oldValues: ['status' => 'pending'],
    newValues: ['status' => 'shipped']
));
```

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
    ->action('created')
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
    ->causer(1) // Assuming causer_id 1 is the admin
    ->where('entity_id', 1)
    ->get();

// Combine multiple scopes for precise filtering
$filteredLogs = EloquentAuditLog::forEntity(User::class)
    ->action('updated')
    ->causer(1)
    ->dateBetween(now()->subDays(7), now())
    ->where('entity_id', 1)
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get();
```

These scopes allow for flexible and efficient querying of audit logs, making it easier to analyze changes based on action type, date range, or the user responsible for the change.

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

## Contributing

Contributions are welcome! Please follow the guidelines in [CONTRIBUTING.md](CONTRIBUTING.md) for submitting pull requests, reporting issues, or suggesting features.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).