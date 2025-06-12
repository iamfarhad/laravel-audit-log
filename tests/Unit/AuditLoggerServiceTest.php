<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogJob;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogSyncJob;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Mockery;

final class AuditLoggerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_can_log_single_audit_synchronously(): void
    {
        // Arrange
        config(['audit-logger.queue.enabled' => false]);

        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $auditLog = new AuditLog(
            entityType: 'App\\Models\\User',
            entityId: '1',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'John Doe'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );
        $mockDriver->shouldReceive('store')->once()->with($auditLog);

        $auditLogger = new AuditLogger($mockDriver);

        // Act
        $auditLogger->log($auditLog);

        // Assert - sync jobs execute immediately and don't go through the queue
        Queue::assertNothingPushed();
    }

    public function test_can_log_single_audit_asynchronously(): void
    {
        // Arrange
        config(['audit-logger.queue.enabled' => true]);

        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $auditLog = new AuditLog(
            entityType: 'App\Models\User',
            entityId: '1',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'John Doe'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );

        $auditLogger = new AuditLogger($mockDriver);

        // Act
        $auditLogger->log($auditLog);

        // Assert
        Queue::assertPushed(ProcessAuditLogJob::class, function ($job) use ($auditLog) {
            return $job->log === $auditLog;
        });
        Queue::assertNotPushed(ProcessAuditLogSyncJob::class);
    }

    public function test_can_log_batch_of_audits_synchronously(): void
    {
        // Arrange
        config(['audit-logger.queue.enabled' => false]);

        $mockDriver = Mockery::mock(AuditDriverInterface::class);

        $auditLog1 = new AuditLog(
            entityType: 'App\\Models\\User',
            entityId: '1',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'User 1'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );

        $auditLog2 = new AuditLog(
            entityType: 'App\\Models\\User',
            entityId: '2',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'User 2'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );

        $mockDriver->shouldReceive('store')->once()->with($auditLog1);
        $mockDriver->shouldReceive('store')->once()->with($auditLog2);

        $auditLogger = new AuditLogger($mockDriver);

        // Act
        $auditLogger->batch([$auditLog1, $auditLog2]);

        // Assert - should not dispatch any jobs for synchronous batch processing
        Queue::assertNothingPushed();
    }

    public function test_can_log_batch_of_audits_asynchronously(): void
    {
        // Arrange
        config(['audit-logger.queue.enabled' => true]);

        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $auditLog1 = new AuditLog(
            entityType: 'App\Models\User',
            entityId: '1',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'User 1'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );

        $auditLog2 = new AuditLog(
            entityType: 'App\Models\User',
            entityId: '2',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'User 2'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );

        $auditLogger = new AuditLogger($mockDriver);

        // Act
        $auditLogger->batch([$auditLog1, $auditLog2]);

        // Assert
        Queue::assertPushed(ProcessAuditLogJob::class, 2);
        Queue::assertPushed(ProcessAuditLogJob::class, function ($job) use ($auditLog1) {
            return $job->log === $auditLog1;
        });
        Queue::assertPushed(ProcessAuditLogJob::class, function ($job) use ($auditLog2) {
            return $job->log === $auditLog2;
        });
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

    public function test_can_get_source_from_console(): void
    {
        // Arrange
        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $auditLogger = new AuditLogger($mockDriver);

        // Mock console environment
        $this->app['env'] = 'testing';
        request()->server->set('argv', ['artisan', 'migrate:fresh']);

        // Act
        $source = $auditLogger->getSource();

        // Assert
        $this->assertEquals('migrate:fresh', $source);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
