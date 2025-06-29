<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

final class ConnectionTest extends TestCase
{
    public function test_driver_accepts_connection_parameter(): void
    {
        $driver = new MySQLDriver('testbench');

        // Test that the driver can check table existence
        $this->assertTrue($driver->storageExistsForEntity('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User'));
    }

    public function test_model_uses_configured_connection(): void
    {
        config(['audit-logger.drivers.mysql.connection' => 'testbench']);

        $model = EloquentAuditLog::forEntity('iamfarhad\\LaravelAuditLog\\Tests\\Mocks\\User');

        $this->assertEquals('testbench', $model->getConnectionName());
    }

    public function test_driver_creates_table_on_specified_connection(): void
    {
        $driver = new MySQLDriver('testbench');

        // Drop the table if it exists
        Schema::connection('testbench')->dropIfExists('audit_test_entities_logs');

        // Create storage for entity
        $driver->createStorageForEntity('App\\Models\\TestEntity');

        // Verify the table was created
        $this->assertTrue(Schema::connection('testbench')->hasTable('audit_test_entities_logs'));
    }
}
