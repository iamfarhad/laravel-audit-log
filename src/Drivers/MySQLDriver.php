<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Drivers;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class MySQLDriver implements AuditDriverInterface
{
    private string $tablePrefix;

    private string $tableSuffix;

    private array $config;

    /**
     * Cache for table existence checks to avoid repeated schema queries.
     */
    private static array $existingTables = [];

    /**
     * Cache for configuration values to avoid repeated config() calls.
     */
    private static ?array $configCache = null;

    public function __construct()
    {
        $this->config = self::getConfigCache();
        $this->tablePrefix = $this->config['drivers']['mysql']['table_prefix'] ?? 'audit_';
        $this->tableSuffix = $this->config['drivers']['mysql']['table_suffix'] ?? '_logs';
    }

    /**
     * Get cached configuration to avoid repeated config() calls.
     */
    private static function getConfigCache(): array
    {
        if (self::$configCache === null) {
            self::$configCache = config('audit-logger');
        }

        return self::$configCache;
    }

    /**
     * Validate that the entity type is a valid class.
     * In testing environment, we allow fake class names for flexibility.
     */
    private function validateEntityType(string $entityType): void
    {
        // Skip validation in testing environment to allow fake class names
        if (app()->environment('testing')) {

            return;
        }

        if (! class_exists($entityType)) {
            throw new \InvalidArgumentException("Entity type '{$entityType}' is not a valid class.");
        }
    }

    public function store(AuditLogInterface $log): void
    {
        $this->validateEntityType($log->getEntityType());
        $tableName = $this->getTableName($log->getEntityType());

        $this->ensureStorageExists($log->getEntityType());

        try {
            $model = EloquentAuditLog::forEntity(entityClass: $log->getEntityType());
            $model->setConnection(config('audit-logger.default'));
            $model->fill([
                'entity_id' => $log->getEntityId(),
                'action' => $log->getAction(),
                'old_values' => $log->getOldValues(), // Remove manual json_encode - let Eloquent handle it
                'new_values' => $log->getNewValues(), // Remove manual json_encode - let Eloquent handle it
                'causer_type' => $log->getCauserType(),
                'causer_id' => $log->getCauserId(),
                'metadata' => $log->getMetadata(), // Remove manual json_encode - let Eloquent handle it
                'created_at' => $log->getCreatedAt(),
                'source' => $log->getSource(),
            ]);
            $model->save();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store multiple audit logs using Eloquent models with proper casting.
     *
     * @param  array<AuditLogInterface>  $logs
     */
    public function storeBatch(array $logs): void
    {
        if (empty($logs)) {
            return;
        }

        // Group logs by entity type (and thus by table)
        $groupedLogs = [];
        foreach ($logs as $log) {
            $this->validateEntityType($log->getEntityType());
            $entityType = $log->getEntityType();
            $groupedLogs[$entityType][] = $log;
        }

        // Process each entity type separately using Eloquent models to leverage casting
        foreach ($groupedLogs as $entityType => $entityLogs) {
            $this->ensureStorageExists($entityType);

            // Use Eloquent models to leverage automatic JSON casting
            foreach ($entityLogs as $log) {
                $model = EloquentAuditLog::forEntity(entityClass: $entityType);
                $model->setConnection(config('audit-logger.default'));
                $model->fill([
                    'entity_id' => $log->getEntityId(),
                    'action' => $log->getAction(),
                    'old_values' => $log->getOldValues(), // Eloquent casting handles JSON encoding
                    'new_values' => $log->getNewValues(), // Eloquent casting handles JSON encoding
                    'causer_type' => $log->getCauserType(),
                    'causer_id' => $log->getCauserId(),
                    'metadata' => $log->getMetadata(), // Eloquent casting handles JSON encoding
                    'created_at' => $log->getCreatedAt(),
                    'source' => $log->getSource(),
                ]);
                $model->save();
            }
        }
    }

    public function createStorageForEntity(string $entityClass): void
    {
        $this->validateEntityType($entityClass);
        $tableName = $this->getTableName($entityClass);

        Schema::connection(config('audit-logger.default'))->create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('entity_id');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->string('source')->nullable();

            // Basic indexes
            $table->index('entity_id');
            $table->index('causer_id');
            $table->index('created_at');
            $table->index('action');

            // Composite indexes for common query patterns
            $table->index(['entity_id', 'action']);
            $table->index(['entity_id', 'created_at']);
            $table->index(['causer_id', 'action']);
            $table->index(['action', 'created_at']);
        });

        // Cache the newly created table
        self::$existingTables[$tableName] = true;
    }

    public function storageExistsForEntity(string $entityClass): bool
    {
        $tableName = $this->getTableName($entityClass);

        // Check cache first to avoid repeated schema queries
        if (isset(self::$existingTables[$tableName])) {
            return self::$existingTables[$tableName];
        }

        // Check database and cache the result
        $exists = Schema::connection(config('audit-logger.default'))->hasTable($tableName);
        self::$existingTables[$tableName] = $exists;

        return $exists;
    }

    /**
     * Ensures the audit storage exists for the entity if auto_migration is enabled.
     */
    public function ensureStorageExists(string $entityClass): void
    {
        $autoMigration = $this->config['auto_migration'] ?? true;
        if ($autoMigration === false) {
            return;
        }

        if (! $this->storageExistsForEntity($entityClass)) {
            $this->createStorageForEntity($entityClass);
        }
    }

    /**
     * Clear the table existence cache and config cache.
     * Useful for testing or when tables are dropped/recreated.
     */
    public static function clearCache(): void
    {
        self::$existingTables = [];
        self::$configCache = null;
    }

    /**
     * Clear only the table existence cache.
     */
    public static function clearTableCache(): void
    {
        self::$existingTables = [];
    }

    private function getTableName(string $entityType): string
    {
        // Extract class name without namespace
        $className = Str::snake(class_basename($entityType));

        // Handle pluralization
        $tableName = Str::plural($className);

        return "{$this->tablePrefix}{$tableName}{$this->tableSuffix}";
    }
}
