<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use iamfarhad\LaravelAuditLog\AuditLoggerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run the migrations
        $this->setUpDatabase();
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

        // Configure audit logger
        $app['config']->set('audit-logger.default', 'mysql');
        $app['config']->set('audit-logger.drivers.mysql.connection', 'testbench');

        // Disable auto migration since we'll create the tables manually in setUpDatabase
        $app['config']->set('audit-logger.auto_migration', false);
        $app['config']->set('audit-logger.batch.enabled', false);

        // Set global excluded fields
        $app['config']->set('audit-logger.fields.exclude', [
            'remember_token',
            'updated_at',
            'created_at',
        ]);
    }

    protected function setUpDatabase(): void
    {
        // Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Create posts table
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Create model-specific audit tables
        $auditTables = [
            'audit_users_logs',
            'audit_posts_logs',
        ];

        foreach ($auditTables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('entity_id');
                $table->string('action');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('causer_type')->nullable();
                $table->string('causer_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');

                $table->index('entity_id');
                $table->index(['causer_type', 'causer_id']);
                $table->index('created_at');
            });
        }
    }
}
