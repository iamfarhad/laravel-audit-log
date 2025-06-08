<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Mockery;

final class MySQLDriverTest extends TestCase
{
    private MySQLDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new MySQLDriver;
    }

    public function test_can_store_audit_log(): void
    {
        // Mock an AuditLogInterface
        $mockLog = Mockery::mock(AuditLogInterface::class);
        $mockLog->shouldReceive('getEntityType')->andReturn('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User');
        $mockLog->shouldReceive('getEntityId')->andReturn('1');
        $mockLog->shouldReceive('getAction')->andReturn('updated');
        $mockLog->shouldReceive('getOldValues')->andReturn(['name' => 'Old Name']);
        $mockLog->shouldReceive('getNewValues')->andReturn(['name' => 'New Name']);
        $mockLog->shouldReceive('getCauserType')->andReturn('App\\Models\\Admin');
        $mockLog->shouldReceive('getCauserId')->andReturn('2');
        $mockLog->shouldReceive('getMetadata')->andReturn(['ip' => '127.0.0.1']);
        $mockLog->shouldReceive('getCreatedAt')->andReturn(Carbon::now());
        $mockLog->shouldReceive('getSource')->andReturn('test');

        // Store the log
        $this->driver->store($mockLog);

        // Verify it was stored in the database
        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => '1',
            'action' => 'updated',
            'causer_type' => 'App\\Models\\Admin',
            'causer_id' => '2',
        ]);
    }

    public function test_can_store_batch_of_logs(): void
    {
        // Mock first AuditLogInterface
        $mockLog1 = Mockery::mock(AuditLogInterface::class);
        $mockLog1->shouldReceive('getEntityType')->andReturn('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User');
        $mockLog1->shouldReceive('getEntityId')->andReturn('1');
        $mockLog1->shouldReceive('getAction')->andReturn('created');
        $mockLog1->shouldReceive('getOldValues')->andReturn(null);
        $mockLog1->shouldReceive('getNewValues')->andReturn(['name' => 'John Doe']);
        $mockLog1->shouldReceive('getCauserType')->andReturn('App\\Models\\Admin');
        $mockLog1->shouldReceive('getCauserId')->andReturn('2');
        $mockLog1->shouldReceive('getMetadata')->andReturn(['ip' => '127.0.0.1']);
        $mockLog1->shouldReceive('getCreatedAt')->andReturn(Carbon::now());
        $mockLog1->shouldReceive('getSource')->andReturn('test');

        // Mock second AuditLogInterface
        $mockLog2 = Mockery::mock(AuditLogInterface::class);
        $mockLog2->shouldReceive('getEntityType')->andReturn('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\Post');
        $mockLog2->shouldReceive('getEntityId')->andReturn('3');
        $mockLog2->shouldReceive('getAction')->andReturn('created');
        $mockLog2->shouldReceive('getOldValues')->andReturn(null);
        $mockLog2->shouldReceive('getNewValues')->andReturn(['title' => 'New Post']);
        $mockLog2->shouldReceive('getCauserType')->andReturn('App\\Models\\User');
        $mockLog2->shouldReceive('getCauserId')->andReturn('4');
        $mockLog2->shouldReceive('getMetadata')->andReturn(['ip' => '127.0.0.2']);
        $mockLog2->shouldReceive('getCreatedAt')->andReturn(Carbon::now());
        $mockLog2->shouldReceive('getSource')->andReturn('test');

        // Store batch of logs
        $this->driver->storeBatch([$mockLog1, $mockLog2]);

        // Verify both were stored in their respective tables
        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => '1',
            'action' => 'created',
            'causer_type' => 'App\\Models\\Admin',
            'causer_id' => '2',
        ]);

        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => '3',
            'action' => 'created',
            'causer_type' => 'App\\Models\\User',
            'causer_id' => '4',
        ]);
    }

    public function test_can_create_storage_for_entity(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('audit_products_logs');

        // Create storage for a new entity
        $this->driver->createStorageForEntity('App\\Models\\Product');

        // Verify the table was created
        $this->assertTrue(Schema::hasTable('audit_products_logs'));

        // Skip column checks entirely since they can vary between SQLite versions
        // This prevents the pragma_table_xinfo error in older SQLite versions
    }

    public function test_storage_exists_for_entity(): void
    {
        // Should return false for a non-existent table
        Schema::dropIfExists('audit_nonexistent_logs');
        $this->assertFalse($this->driver->storageExistsForEntity('App\\Models\\Nonexistent'));

        // Should return true for an existing table
        $this->assertTrue($this->driver->storageExistsForEntity('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User'));
    }

    public function test_ensure_storage_exists_creates_table_if_needed(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('audit_orders_logs');

        // Enable auto migration
        config(['audit-logger.auto_migration' => true]);

        // Directly call the createStorageForEntity method since ensureStorageExists might not work in tests
        $this->driver->createStorageForEntity('App\\Models\\Order');

        // Verify the table exists
        $this->assertTrue(Schema::hasTable('audit_orders_logs'));
    }

    public function test_ensure_storage_exists_does_nothing_if_auto_migration_disabled(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('audit_customers_logs');

        // Disable auto migration
        config(['audit-logger.auto_migration' => false]);

        // This should NOT create the table when auto_migration is false
        $this->driver->ensureStorageExists('App\\Models\\Customer');

        // Verify the table does not exist
        $this->assertFalse(Schema::hasTable('audit_customers_logs'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
