<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Drivers;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use iamfarhad\LaravelAuditLog\Models\AuditLog;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

final class MySQLDriver implements AuditDriverInterface
{
    private Connection $connection;

    private string $tablePrefix;

    private string $tableSuffix;

    public function __construct(array $config = [])
    {
        $connectionName = $config['connection'] ?? config('database.default');
        $this->connection = app('db')->connection($connectionName);
        $this->tablePrefix = $config['table_prefix'] ?? 'audit_';
        $this->tableSuffix = $config['table_suffix'] ?? '_logs';
    }

    public function store(AuditLogInterface $log): void
    {
        Log::info('Entering store method for audit log', [
            'entity_type' => $log->getEntityType(),
            'entity_id' => $log->getEntityId(),
            'action' => $log->getAction(),
        ]);

        $tableName = $this->getTableName($log->getEntityType());

        try {
            $this->connection->table($tableName)->insert([
                'entity_id' => $log->getEntityId(),
                'action' => $log->getAction(),
                'old_values' => $log->getOldValues() ? json_encode($log->getOldValues()) : null,
                'new_values' => $log->getNewValues() ? json_encode($log->getNewValues()) : null,
                'causer_type' => $log->getCauserType(),
                'causer_id' => $log->getCauserId(),
                'metadata' => json_encode($log->getMetadata()),
                'created_at' => $log->getCreatedAt(),
            ]);
            Log::debug('Audit log inserted into database', [
                'table' => $tableName,
                'entity_id' => $log->getEntityId(),
                'action' => $log->getAction(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store audit log in database', [
                'table' => $tableName,
                'entity_id' => $log->getEntityId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Legacy method to maintain interface compatibility.
     * Simply stores logs one by one instead of in batch.
     *
     * @param  array<AuditLogInterface>  $logs
     */
    public function storeBatch(array $logs): void
    {
        foreach ($logs as $log) {
            $this->store($log);
        }
    }

    public function getLogsForEntity(string $entityType, string|int $entityId, array $options = []): array
    {
        $tableName = $this->getTableName($entityType);

        if (! Schema::hasTable($tableName)) {
            return [];
        }

        $query = $this->connection->table($tableName)
            ->where('entity_id', $entityId);

        $this->applyFilters($query, $options);

        $records = $query->get();
        $logs = [];

        foreach ($records as $record) {
            $logs[] = new AuditLog(
                entityType: $entityType,
                entityId: $record->entity_id,
                action: $record->action,
                oldValues: $record->old_values ? json_decode($record->old_values, true) : null,
                newValues: $record->new_values ? json_decode($record->new_values, true) : null,
                causerType: $record->causer_type,
                causerId: $record->causer_id,
                metadata: json_decode($record->metadata, true) ?? [],
                createdAt: new Carbon($record->created_at)
            );
        }

        return $logs;
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
        if (! config('audit-logger.auto_migration', true)) {
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

        return $this->tablePrefix.$tableName.$this->tableSuffix;
    }

    private function applyFilters(Builder $query, array $options): void
    {
        if (isset($options['limit'])) {
            $query->limit((int) $options['limit']);
        }

        if (isset($options['offset'])) {
            $query->offset((int) $options['offset']);
        }

        if (isset($options['action'])) {
            $query->where('action', $options['action']);
        }

        if (isset($options['from_date'])) {
            $query->where('created_at', '>=', new Carbon($options['from_date']));
        }

        if (isset($options['to_date'])) {
            $query->where('created_at', '<=', new Carbon($options['to_date']));
        }

        // Sort by created_at in descending order by default
        $query->orderBy('created_at', $options['sort'] ?? 'desc');
    }
}
