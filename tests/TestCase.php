<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests;

use iamfarhad\LaravelAuditLog\AuditLoggerServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [AuditLoggerServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('audit-logger.default', 'mysql');
        $app['config']->set('audit-logger.drivers.mysql.connection', 'testbench');
        $app['config']->set('audit-logger.drivers.postgresql.connection', 'testbench');
        $app['config']->set('audit-logger.auto_migration', false);
        $app['config']->set('audit-logger.batch.enabled', false);
        $app['config']->set('audit-logger.security.hashing.enabled', false);
        $app['config']->set('audit-logger.fields.exclude', [
            'password',
            'remember_token',
            'updated_at',
            'created_at',
        ]);
        $app['config']->set('audit-logger.fields.redact', []);
        $app['config']->set('audit-logger.fields.transformers', []);
    }

    protected function setUpDatabase(): void
    {
        Schema::connection('testbench')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('testbench')->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        foreach (['audit_users_logs', 'audit_posts_logs'] as $tableName) {
            Schema::connection('testbench')->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('entity_id');
                $table->string('action');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('causer_type')->nullable();
                $table->string('causer_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');
                $table->string('source')->nullable();
                $table->string('audit_hash', 128)->nullable();
                $table->string('previous_hash', 128)->nullable();
                $table->timestamp('anonymized_at')->nullable();
                $table->index('entity_id');
                $table->index(['causer_type', 'causer_id']);
                $table->index('created_at');
                $table->index('audit_hash');
                $table->index('previous_hash');
            });
        }
    }
}
