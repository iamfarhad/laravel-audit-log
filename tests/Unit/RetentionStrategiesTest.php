<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\DTOs\RetentionConfig;
use iamfarhad\LaravelAuditLog\Services\Retention\AnonymizeRetentionStrategy;
use iamfarhad\LaravelAuditLog\Services\Retention\ArchiveRetentionStrategy;
use iamfarhad\LaravelAuditLog\Services\Retention\DeleteRetentionStrategy;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

final class RetentionStrategiesTest extends TestCase
{
    #[Test]
    public function delete_strategy_has_correct_name(): void
    {
        $strategy = new DeleteRetentionStrategy;

        $this->assertEquals('delete', $strategy->getName());
    }

    #[Test]
    public function delete_strategy_can_always_execute(): void
    {
        $strategy = new DeleteRetentionStrategy;
        $config = new RetentionConfig(
            enabled: true,
            days: 365,
            strategy: 'delete',
            batchSize: 1000,
            anonymizeAfterDays: 180,
            archiveConnection: null,
            entityType: 'TestEntity',
            tableName: 'test_logs',
        );

        $this->assertTrue($strategy->canExecute($config));
    }

    #[Test]
    public function anonymize_strategy_has_correct_name(): void
    {
        $strategy = new AnonymizeRetentionStrategy;

        $this->assertEquals('anonymize', $strategy->getName());
    }

    #[Test]
    public function anonymize_strategy_can_always_execute(): void
    {
        $strategy = new AnonymizeRetentionStrategy;
        $config = new RetentionConfig(
            enabled: true,
            days: 365,
            strategy: 'anonymize',
            batchSize: 1000,
            anonymizeAfterDays: 180,
            archiveConnection: null,
            entityType: 'TestEntity',
            tableName: 'test_logs',
        );

        $this->assertTrue($strategy->canExecute($config));
    }

    #[Test]
    public function archive_strategy_has_correct_name(): void
    {
        $strategy = new ArchiveRetentionStrategy;

        $this->assertEquals('archive', $strategy->getName());
    }

    #[Test]
    public function archive_strategy_cannot_execute_without_connection(): void
    {
        $strategy = new ArchiveRetentionStrategy;
        $config = new RetentionConfig(
            enabled: true,
            days: 365,
            strategy: 'archive',
            batchSize: 1000,
            anonymizeAfterDays: 180,
            archiveConnection: null,
            entityType: 'TestEntity',
            tableName: 'test_logs',
        );

        $this->assertFalse($strategy->canExecute($config));
    }

    #[Test]
    public function archive_strategy_can_execute_with_valid_connection(): void
    {
        Config::set('database.connections.archive', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $strategy = new ArchiveRetentionStrategy;
        $config = new RetentionConfig(
            enabled: true,
            days: 365,
            strategy: 'archive',
            batchSize: 1000,
            anonymizeAfterDays: 180,
            archiveConnection: 'archive',
            entityType: 'TestEntity',
            tableName: 'test_logs',
        );

        $this->assertTrue($strategy->canExecute($config));
    }

    #[Test]
    public function retention_config_helper_methods_work(): void
    {
        $config = new RetentionConfig(
            enabled: true,
            days: 365,
            strategy: 'delete',
            batchSize: 1000,
            anonymizeAfterDays: 180,
            archiveConnection: null,
            entityType: 'TestEntity',
            tableName: 'test_logs',
        );

        $this->assertTrue($config->isDeleteStrategy());
        $this->assertFalse($config->isArchiveStrategy());
        $this->assertFalse($config->isAnonymizeStrategy());
        $this->assertTrue($config->shouldAnonymizeFirst());

        $archiveConfig = new RetentionConfig(
            enabled: true,
            days: 365,
            strategy: 'archive',
            batchSize: 1000,
            anonymizeAfterDays: 180,
            archiveConnection: 'archive',
            entityType: 'TestEntity',
            tableName: 'test_logs',
        );

        $this->assertFalse($archiveConfig->isDeleteStrategy());
        $this->assertTrue($archiveConfig->isArchiveStrategy());
        $this->assertFalse($archiveConfig->isAnonymizeStrategy());
    }

    #[Test]
    public function retention_config_from_array_works(): void
    {
        $configArray = [
            'enabled' => true,
            'days' => 180,
            'strategy' => 'anonymize',
            'batch_size' => 500,
            'anonymize_after_days' => 90,
            'archive_connection' => 'archive_db',
        ];

        $config = RetentionConfig::fromArray($configArray, 'TestEntity', 'test_logs');

        $this->assertTrue($config->enabled);
        $this->assertEquals(180, $config->days);
        $this->assertEquals('anonymize', $config->strategy);
        $this->assertEquals(500, $config->batchSize);
        $this->assertEquals(90, $config->anonymizeAfterDays);
        $this->assertEquals('archive_db', $config->archiveConnection);
        $this->assertEquals('TestEntity', $config->entityType);
        $this->assertEquals('test_logs', $config->tableName);
    }

    #[Test]
    public function retention_config_uses_defaults_for_missing_values(): void
    {
        $configArray = [
            'enabled' => true,
        ];

        $config = RetentionConfig::fromArray($configArray, 'TestEntity', 'test_logs');

        $this->assertTrue($config->enabled);
        $this->assertEquals(365, $config->days); // default
        $this->assertEquals('delete', $config->strategy); // default
        $this->assertEquals(1000, $config->batchSize); // default
        $this->assertEquals(180, $config->anonymizeAfterDays); // default
        $this->assertNull($config->archiveConnection); // default
    }
}
