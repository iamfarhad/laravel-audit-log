<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Drivers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

final class MySQLDriver implements AuditDriverInterface
{
    private string $tablePrefix;

    private string $tableSuffix;

    private array $config;

    public function __construct()
    {
        $this->config = config('audit-logger');
        $this->tablePrefix = $this->config['drivers']['mysql']['table_prefix'] ?? 'audit_';
        $this->tableSuffix = $this->config['drivers']['mysql']['table_suffix'] ?? '_logs';
    }

    public function store(AuditLogInterface $log): void
    {
        $tableName = $this->getTableName($log->getEntityType());

        $this->ensureStorageExists($log->getEntityType());

        try {
            $oldValues = $log->getOldValues();
            $newValues = $log->getNewValues();

            $model = EloquentAuditLog::forEntity(entityClass: $log->getEntityType());
            $model->fill([
                'entity_id' => $log->getEntityId(),
                'action' => $log->getAction(),
                'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
                'new_values' => $newValues !== null ? json_encode($newValues) : null,
                'causer_type' => $log->getCauserType(),
                'causer_id' => $log->getCauserId(),
                'metadata' => json_encode($log->getMetadata()),
                'created_at' => $log->getCreatedAt(),
                'source' => $log->getSource(),
            ]);
            $model->save();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store multiple audit logs.
     *
     * @param  array<AuditLogInterface>  $logs
     */
    public function storeBatch(array $logs): void
    {
        foreach ($logs as $log) {
            $this->store($log);
        }
    }

    public function createStorageForEntity(string $entityClass): void
    {
        $tableName = $this->getTableName($entityClass);

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
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

            $table->index('entity_id');
            $table->index('causer_id');
            $table->index('created_at');
        });
    }

    public function storageExistsForEntity(string $entityClass): bool
    {
        return Schema::hasTable($this->getTableName($entityClass));
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

    private function getTableName(string $entityType): string
    {
        // Extract class name without namespace
        $className = Str::snake(class_basename($entityType));

        // Handle pluralization
        $tableName = Str::plural($className);

        return "{$this->tablePrefix}{$tableName}{$this->tableSuffix}";
    }
}
