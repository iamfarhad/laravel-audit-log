<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Mockery;

final class AuditLoggerServiceTest extends TestCase
{
    public function test_can_log_single_audit(): void
    {
        // Mock the driver
        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $mockDriver->shouldReceive('store')->once()->andReturn(true);

        // Mock the audit log
        $mockLog = Mockery::mock(AuditLogInterface::class);

        // Create the service with the mock driver
        $auditLogger = new AuditLogger($mockDriver);

        // Call the log method and assert it was successful
        $auditLogger->log($mockLog);

        // Verify with Mockery's built-in assertion that the store method was called
        $this->assertTrue(true);
    }

    public function test_can_log_batch_of_audits(): void
    {
        // Mock the driver
        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $mockDriver->shouldReceive('storeBatch')->once()->andReturn(true);

        // Mock the audit logs
        $mockLog1 = Mockery::mock(AuditLogInterface::class);
        $mockLog2 = Mockery::mock(AuditLogInterface::class);

        // Create the service with the mock driver
        $auditLogger = new AuditLogger($mockDriver);

        // Call the batch method and assert it was successful
        $auditLogger->batch([$mockLog1, $mockLog2]);

        // Verify with Mockery's built-in assertion that the storeBatch method was called
        $this->assertTrue(true);
    }

    public function test_can_get_mysql_driver(): void
    {
        $auditLogger = AuditLogger::getDriver('mysql');

        $this->assertInstanceOf(AuditLogger::class, $auditLogger);
    }

    public function test_throws_exception_for_invalid_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver nonexistent not found');

        AuditLogger::getDriver('nonexistent');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
