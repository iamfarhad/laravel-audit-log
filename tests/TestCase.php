<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use iamfarhad\LaravelAuditLog\AuditLoggerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AuditLoggerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup MongoDB connection for testing
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'host' => env('MONGODB_HOST', '127.0.0.1'),
            'port' => env('MONGODB_PORT', 27017),
            'database' => env('MONGODB_DATABASE', 'audit_test'),
            'username' => env('MONGODB_USERNAME', ''),
            'password' => env('MONGODB_PASSWORD', ''),
        ]);

        // Configure audit logger
        $app['config']->set('audit-logger.default', 'mysql');
        $app['config']->set('audit-logger.drivers.mysql.connection', 'testbench');
        $app['config']->set('audit-logger.batch.enabled', false);
        $app['config']->set('audit-logger.auto_migration', true);
    }
}
