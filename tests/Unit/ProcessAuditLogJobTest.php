<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogJob;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Mockery;

final class ProcessAuditLogJobTest extends TestCase
{
    public function test_it_can_process_audit_log(): void
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

        $job = new ProcessAuditLogJob($auditLog, $mockDriver);

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

        $job = new ProcessAuditLogJob($auditLog, $mockDriver);

        // Expect
        $mockDriver->shouldReceive('store')
            ->once()
            ->with($auditLog);

        // Act
        $job->handle();

        // Assert
        $this->expectNotToPerformAssertions();
    }

    public function test_it_configures_queue_settings(): void
    {
        // Arrange
        config([
            'audit-logger.queue.queue_name' => 'custom-audit',
            'audit-logger.queue.connection' => 'redis',
            'audit-logger.queue.delay' => 60,
        ]);

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

        // Act
        $job = new ProcessAuditLogJob($auditLog, Mockery::mock(\iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface::class));

        // Assert
        $this->assertEquals('custom-audit', $job->queue);
        $this->assertEquals('redis', $job->connection);
        $this->assertNotNull($job->delay);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
