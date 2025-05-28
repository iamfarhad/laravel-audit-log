<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use iamfarhad\LaravelAuditLog\Models\AuditLog;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Drivers\MongoDBDriver;
use iamfarhad\LaravelAuditLog\Config\AuditLoggerConfig;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

final class AuditLogger
{
    private array $drivers = [];

    private ?string $defaultDriver = null;

    public function __construct(
        private readonly CauserResolverInterface $causerResolver,
        private readonly array $config = [],
        // private readonly AuditLoggerConfig $auditLoggerConfig,
    ) {
        $this->defaultDriver = $config['default'] ?? 'mysql';
    }

    /**
     * Log an audit event.
     */
    public function log(
        string $entityType,
        string|int $entityId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = []
    ): void {
        // Ensure storage exists before logging
        $this->ensureStorageExists($entityType);

        // Create a log object using the existing AuditLog class
        $log = new AuditLog(
            entityType: $entityType,
            entityId: $entityId,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            causerType: null,
            causerId: null,
            metadata: $metadata,
            createdAt: Carbon::now()
        );

        $this->driver()->store($log);
    }

    /**
     * Get logs for a specific entity.
     *
     * @return array<AuditLogInterface>
     */
    public function getLogsForEntity(
        string $entityType,
        string|int $entityId,
        array $options = []
    ): array {
        return $this->driver()->getLogsForEntity($entityType, $entityId, $options);
    }

    /**
     * Create storage for a new entity type.
     */
    public function createStorageForEntity(string $entityClass): void
    {
        $this->driver()->createStorageForEntity($entityClass);
    }

    /**
     * Check if storage exists for an entity type.
     */
    public function storageExistsForEntity(string $entityClass): bool
    {
        return $this->driver()->storageExistsForEntity($entityClass);
    }

    /**
     * Ensures the audit storage exists for the entity if auto_migration is enabled.
     */
    public function ensureStorageExists(string $entityClass): void
    {
        $this->driver()->ensureStorageExists($entityClass);
    }

    /**
     * Get the audit driver instance.
     */
    public function driver(?string $name = null): AuditDriverInterface
    {
        $name = $name ?? $this->defaultDriver;

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a new driver instance.
     */
    private function createDriver(string $name): AuditDriverInterface
    {
        $config = $this->config['drivers'][$name] ?? [];

        return match ($name) {
            'mysql' => app(MySQLDriver::class, ['config' => $config]),
            'mongodb' => new MongoDBDriver($config),
            default => throw new InvalidArgumentException("Unsupported audit driver: {$name}")
        };
    }

    // public function setStorage(AuditStorageInterface $storage): void
    // {
    //     $this->storage = $storage;
    // }
}
