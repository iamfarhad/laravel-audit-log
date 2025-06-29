<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\RetentionServiceInterface;
use iamfarhad\LaravelAuditLog\Contracts\RetentionStrategyInterface;
use iamfarhad\LaravelAuditLog\DTOs\RetentionConfig;
use iamfarhad\LaravelAuditLog\DTOs\RetentionResult;
use iamfarhad\LaravelAuditLog\Services\Retention\AnonymizeRetentionStrategy;
use iamfarhad\LaravelAuditLog\Services\Retention\ArchiveRetentionStrategy;
use iamfarhad\LaravelAuditLog\Services\Retention\DeleteRetentionStrategy;
use Illuminate\Support\Str;

final class RetentionService implements RetentionServiceInterface
{
    /**
     * @var array<string, RetentionStrategyInterface>
     */
    private array $strategies;

    public function __construct()
    {
        $this->strategies = [
            'delete' => new DeleteRetentionStrategy,
            'archive' => new ArchiveRetentionStrategy,
            'anonymize' => new AnonymizeRetentionStrategy,
        ];
    }

    public function runCleanup(): RetentionResult
    {
        if (! $this->isRetentionEnabled()) {
            return RetentionResult::empty();
        }

        $startTime = microtime(true);
        $overallResult = RetentionResult::empty();

        $entities = config('audit-logger.entities', []);

        foreach ($entities as $entityType => $entityConfig) {
            try {
                $result = $this->runCleanupForEntity($entityType);
                $overallResult = $overallResult->merge($result);
            } catch (\Exception $e) {
                $overallResult = $overallResult->addError(
                    "Failed to process entity {$entityType}: ".$e->getMessage()
                );
            }
        }

        $executionTime = microtime(true) - $startTime;

        return new RetentionResult(
            totalProcessed: $overallResult->totalProcessed,
            entitiesProcessed: $overallResult->entitiesProcessed,
            errors: $overallResult->errors,
            executionTime: $executionTime,
        );
    }

    public function runCleanupForEntity(string $entityType): RetentionResult
    {
        if (! $this->isRetentionEnabledForEntity($entityType)) {
            return RetentionResult::empty();
        }

        $startTime = microtime(true);

        try {
            $retentionConfig = $this->buildRetentionConfig($entityType);
            $strategy = $this->getStrategy($retentionConfig->strategy);

            if (! $strategy->canExecute($retentionConfig)) {
                return RetentionResult::empty()->addError(
                    "Strategy '{$retentionConfig->strategy}' cannot execute for entity {$entityType}"
                );
            }

            $processed = $strategy->execute($entityType, $retentionConfig);
            $executionTime = microtime(true) - $startTime;

            return RetentionResult::fromSingle($entityType, $processed, $executionTime);
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            return RetentionResult::fromSingle($entityType, 0, $executionTime)
                ->addError("Error processing {$entityType}: ".$e->getMessage());
        }
    }

    public function getRetentionConfig(string $entityType): ?array
    {
        $entities = config('audit-logger.entities', []);
        $globalConfig = config('audit-logger.retention', []);

        if (! isset($entities[$entityType])) {
            return null;
        }

        $entityConfig = $entities[$entityType];

        // Merge global retention config with entity-specific config
        return array_merge($globalConfig, $entityConfig['retention'] ?? []);
    }

    public function isRetentionEnabled(): bool
    {
        return config('audit-logger.retention.enabled', false);
    }

    public function isRetentionEnabledForEntity(string $entityType): bool
    {
        if (! $this->isRetentionEnabled()) {
            return false;
        }

        $retentionConfig = $this->getRetentionConfig($entityType);

        return $retentionConfig !== null && ($retentionConfig['enabled'] ?? true);
    }

    private function buildRetentionConfig(string $entityType): RetentionConfig
    {
        $config = $this->getRetentionConfig($entityType);

        if ($config === null) {
            throw new \InvalidArgumentException("No retention config found for entity: {$entityType}");
        }

        $tableName = $this->getTableNameForEntity($entityType);

        return RetentionConfig::fromArray($config, $entityType, $tableName);
    }

    private function getTableNameForEntity(string $entityType): string
    {
        $entities = config('audit-logger.entities', []);

        if (isset($entities[$entityType]['table'])) {
            return $entities[$entityType]['table'];
        }

        // Generate table name using the same logic as MySQLDriver
        $tablePrefix = config('audit-logger.drivers.mysql.table_prefix', 'audit_');
        $tableSuffix = config('audit-logger.drivers.mysql.table_suffix', '_logs');
        $className = Str::snake(class_basename($entityType));
        $tableName = Str::plural($className);

        return "{$tablePrefix}{$tableName}{$tableSuffix}";
    }

    private function getStrategy(string $strategyName): RetentionStrategyInterface
    {
        if (! isset($this->strategies[$strategyName])) {
            throw new \InvalidArgumentException("Unknown retention strategy: {$strategyName}");
        }

        return $this->strategies[$strategyName];
    }
}
