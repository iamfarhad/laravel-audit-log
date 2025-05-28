<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Drivers;

use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Database\Connection;
use iamfarhad\LaravelAuditLog\Models\AuditLog;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

final class MongoDBDriver implements AuditDriverInterface
{
    private Connection $connection;

    private string $collectionPrefix;

    private string $collectionSuffix;

    public function __construct(array $config = [])
    {
        $connectionName = $config['connection'] ?? 'mongodb';
        $this->connection = app('db')->connection($connectionName);
        $this->collectionPrefix = $config['collection_prefix'] ?? 'audit_';
        $this->collectionSuffix = $config['collection_suffix'] ?? '_logs';
    }

    public function store(AuditLogInterface $log): void
    {
        $collectionName = $this->getCollectionName($log->getEntityType());

        $this->connection->collection($collectionName)->insert([
            'entity_id' => (string) $log->getEntityId(),
            'action' => $log->getAction(),
            'old_values' => $log->getOldValues(),
            'new_values' => $log->getNewValues(),
            'causer_type' => $log->getCauserType(),
            'causer_id' => $log->getCauserId() ? (string) $log->getCauserId() : null,
            'metadata' => $log->getMetadata(),
            'created_at' => $log->getCreatedAt(),
        ]);
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
        $collectionName = $this->getCollectionName($entityType);

        $query = [
            'entity_id' => (string) $entityId,
        ];

        if (isset($options['action'])) {
            $query['action'] = $options['action'];
        }

        if (isset($options['from_date'])) {
            $query['created_at'] = $query['created_at'] ?? [];
            $query['created_at']['$gte'] = new UTCDateTime(strtotime($options['from_date']) * 1000);
        }

        if (isset($options['to_date'])) {
            $query['created_at'] = $query['created_at'] ?? [];
            $query['created_at']['$lte'] = new UTCDateTime(strtotime($options['to_date']) * 1000);
        }

        $cursor = $this->connection->collection($collectionName)
            ->find($query)
            ->orderBy('created_at', $options['sort'] ?? 'desc');

        if (isset($options['limit'])) {
            $cursor->limit((int) $options['limit']);
        }

        if (isset($options['offset'])) {
            $cursor->skip((int) $options['offset']);
        }

        $logs = [];

        foreach ($cursor as $record) {
            $logs[] = new AuditLog(
                entityType: $entityType,
                entityId: $record['entity_id'],
                action: $record['action'],
                oldValues: $record['old_values'] ?? null,
                newValues: $record['new_values'] ?? null,
                causerType: $record['causer_type'] ?? null,
                causerId: $record['causer_id'] ?? null,
                metadata: $record['metadata'] ?? [],
                createdAt: $record['created_at']->toDateTime()
            );
        }

        return $logs;
    }

    public function createStorageForEntity(string $entityClass): void
    {
        // MongoDB collections are created automatically when first document is inserted
        // No need to explicitly create them
    }

    public function storageExistsForEntity(string $entityClass): bool
    {
        $collectionName = $this->getCollectionName($entityClass);
        $collections = $this->connection->listCollections();

        foreach ($collections as $collection) {
            if ($collection->getName() === $collectionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensures the audit storage exists for the entity if auto_migration is enabled.
     * For MongoDB, collections are created automatically when data is inserted,
     * so this method only needs to check the configuration.
     */
    public function ensureStorageExists(string $entityClass): void
    {
        // MongoDB collections are created automatically when data is inserted
        // No need to explicitly create them, just check if auto_migration is enabled
        if (! config('audit-logger.auto_migration', true)) {
            return;
        }

        // No additional action needed for MongoDB as collections are created on first use
    }

    private function getCollectionName(string $entityType): string
    {
        $baseName = Str::snake(class_basename($entityType));
        $pluralName = Str::plural($baseName);

        return $this->collectionPrefix.$pluralName.$this->collectionSuffix;
    }
}
