<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class EloquentAuditLogTest extends TestCase
{
    public function test_can_create_audit_log_model(): void
    {
        $createdAt = Carbon::now();

        $log = new EloquentAuditLog;
        $log->entity_id = '123';
        $log->action = 'updated';
        $log->old_values = ['name' => 'John'];
        $log->new_values = ['name' => 'Jane'];
        $log->causer_type = 'App\\Models\\Admin';
        $log->causer_id = '456';
        $log->metadata = ['ip' => '127.0.0.1'];
        $log->created_at = $createdAt;

        $this->assertEquals('123', $log->entity_id);
        $this->assertEquals('updated', $log->action);
        $this->assertEquals(['name' => 'John'], $log->old_values);
        $this->assertEquals(['name' => 'Jane'], $log->new_values);
        $this->assertEquals('App\\Models\\Admin', $log->causer_type);
        $this->assertEquals('456', $log->causer_id);
        $this->assertEquals(['ip' => '127.0.0.1'], $log->metadata);
        $this->assertEquals($createdAt, $log->created_at);
    }

    public function test_can_generate_correct_table_name_for_entity(): void
    {
        // Test the table name generation directly based on the same logic
        // used in the MySQLDriver and EloquentAuditLog classes

        $prefix = 'audit_';
        $suffix = '_logs';

        $entityClass = 'iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User';
        $className = Str::snake(class_basename($entityClass));
        $tableName = $prefix.Str::plural($className).$suffix;
        $this->assertEquals('audit_users_logs', $tableName);

        $entityClass = 'iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\Post';
        $className = Str::snake(class_basename($entityClass));
        $tableName = $prefix.Str::plural($className).$suffix;
        $this->assertEquals('audit_posts_logs', $tableName);

        $entityClass = 'App\\Models\\Product';
        $className = Str::snake(class_basename($entityClass));
        $tableName = $prefix.Str::plural($className).$suffix;
        $this->assertEquals('audit_products_logs', $tableName);
    }

    public function test_can_instantiate_model_for_entity(): void
    {
        $userAuditLog = EloquentAuditLog::forEntity('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User');
        $this->assertInstanceOf(EloquentAuditLog::class, $userAuditLog);
        $this->assertEquals('audit_users_logs', $userAuditLog->getTable());

        $postAuditLog = EloquentAuditLog::forEntity('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\Post');
        $this->assertInstanceOf(EloquentAuditLog::class, $postAuditLog);
        $this->assertEquals('audit_posts_logs', $postAuditLog->getTable());
    }

    public function test_can_convert_json_fields(): void
    {
        $log = new EloquentAuditLog;
        $log->entity_id = '123';
        $log->action = 'updated';

        // Test setting arrays
        $log->old_values = ['name' => 'Old Name', 'age' => 30];
        $log->new_values = ['name' => 'New Name', 'age' => 31];
        $log->metadata = ['ip' => '127.0.0.1', 'user_agent' => 'Test Browser'];

        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertIsArray($log->metadata);

        $this->assertEquals('Old Name', $log->old_values['name']);
        $this->assertEquals('New Name', $log->new_values['name']);
        $this->assertEquals('127.0.0.1', $log->metadata['ip']);
    }
}
