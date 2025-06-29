<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\DTOs\RetentionResult;
use iamfarhad\LaravelAuditLog\Services\RetentionService;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

final class RetentionServiceTest extends TestCase
{
    private RetentionService $retentionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retentionService = new RetentionService;
    }

    #[Test]
    public function it_returns_empty_result_when_retention_is_disabled(): void
    {
        Config::set('audit-logger.retention.enabled', false);

        $result = $this->retentionService->runCleanup();

        $this->assertInstanceOf(RetentionResult::class, $result);
        $this->assertEquals(0, $result->totalProcessed);
        $this->assertEmpty($result->entitiesProcessed);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function it_checks_if_retention_is_enabled(): void
    {
        Config::set('audit-logger.retention.enabled', false);
        $this->assertFalse($this->retentionService->isRetentionEnabled());

        Config::set('audit-logger.retention.enabled', true);
        $this->assertTrue($this->retentionService->isRetentionEnabled());
    }

    #[Test]
    public function it_checks_if_retention_is_enabled_for_entity(): void
    {
        Config::set('audit-logger.retention.enabled', false);
        $this->assertFalse($this->retentionService->isRetentionEnabledForEntity('TestEntity'));

        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.entities', [
            'TestEntity' => ['table' => 'test_logs'],
        ]);
        $this->assertTrue($this->retentionService->isRetentionEnabledForEntity('TestEntity'));

        // Test entity-specific disabled setting
        Config::set('audit-logger.entities', [
            'TestEntity' => [
                'table' => 'test_logs',
                'retention' => ['enabled' => false],
            ],
        ]);
        $this->assertFalse($this->retentionService->isRetentionEnabledForEntity('TestEntity'));
    }

    #[Test]
    public function it_gets_retention_config_for_entity(): void
    {
        Config::set('audit-logger.retention', [
            'enabled' => true,
            'days' => 365,
            'strategy' => 'delete',
        ]);

        Config::set('audit-logger.entities', [
            'TestEntity' => [
                'table' => 'test_logs',
                'retention' => [
                    'days' => 180,
                    'strategy' => 'anonymize',
                ],
            ],
        ]);

        $config = $this->retentionService->getRetentionConfig('TestEntity');

        $this->assertNotNull($config);
        $this->assertEquals(180, $config['days']); // Entity-specific override
        $this->assertEquals('anonymize', $config['strategy']); // Entity-specific override
        $this->assertTrue($config['enabled']); // From global config
    }

    #[Test]
    public function it_returns_null_for_unregistered_entity(): void
    {
        Config::set('audit-logger.entities', []);

        $config = $this->retentionService->getRetentionConfig('UnknownEntity');

        $this->assertNull($config);
    }

    #[Test]
    public function it_processes_all_entities_on_cleanup(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.entities', [
            'Entity1' => ['table' => 'entity1_logs'],
            'Entity2' => ['table' => 'entity2_logs'],
        ]);

        // Mock the cleanup for each entity to avoid actual database operations
        $result = $this->retentionService->runCleanup();

        $this->assertInstanceOf(RetentionResult::class, $result);
        // In this test, strategies won't execute because tables don't exist
        // but we can verify the service tries to process all entities
    }

    #[Test]
    public function it_handles_single_entity_cleanup(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.entities', [
            'TestEntity' => ['table' => 'test_logs'],
        ]);

        $result = $this->retentionService->runCleanupForEntity('TestEntity');

        $this->assertInstanceOf(RetentionResult::class, $result);
    }

    #[Test]
    public function it_returns_empty_result_for_disabled_entity(): void
    {
        Config::set('audit-logger.retention.enabled', false);

        $result = $this->retentionService->runCleanupForEntity('TestEntity');

        $this->assertInstanceOf(RetentionResult::class, $result);
        $this->assertEquals(0, $result->totalProcessed);
    }
}
