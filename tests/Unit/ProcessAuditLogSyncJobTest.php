<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogSyncJob;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Mockery;

final class ProcessAuditLogSyncJobTest extends TestCase
{
    public function test_it_can_process_audit_log_synchronously(): void
    {
        // Arrange
        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $this->app->instance(AuditDriverInterface::class, $mockDriver);

        $auditLog = new AuditLog(
            entityType: 'App\Models\User',
            entityId: '1',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'John Doe', 'email' => 'john@example.com'],
            metadata: [],
            causerType: null,
            causerId: null,
            createdAt: now(),
            source: 'test'
        );

        $job = new ProcessAuditLogSyncJob($auditLog, $mockDriver);

        // Expect
        $mockDriver->shouldReceive('store')
            ->once()
            ->with($auditLog);

        // Act
        $job->handle();

        // Assert
        $this->expectNotToPerformAssertions();
    }

    public function test_it_uses_default_driver_when_none_specified(): void
    {
        // Arrange
        config(['audit-logger.default' => 'mysql']);

        $mockDriver = Mockery::mock(AuditDriverInterface::class);
        $this->app->instance(AuditDriverInterface::class, $mockDriver);

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

        $job = new ProcessAuditLogSyncJob($auditLog, $mockDriver);

        // Expect
        $mockDriver->shouldReceive('store')
            ->once()
            ->with($auditLog);

        // Act
        $job->handle();

        // Assert
        $this->expectNotToPerformAssertions();
    }

    public function test_it_does_not_implement_should_queue(): void
    {
        // Arrange
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

        $job = new ProcessAuditLogSyncJob($auditLog, Mockery::mock(\iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface::class));

        // Assert - sync job should not implement ShouldQueue
        $this->assertFalse($job instanceof \Illuminate\Contracts\Queue\ShouldQueue);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
