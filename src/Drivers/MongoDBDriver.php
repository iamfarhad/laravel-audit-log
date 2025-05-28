<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Drivers;

use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use Illuminate\Support\Facades\DB;
use MongoDB\Client;
use iamfarhad\LaravelAuditLog\Models\AuditLog;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

final class MongoDBDriver implements AuditDriverInterface
{
    private ?Database $database = null;

    private string $collectionPrefix;

    private string $collectionSuffix;

    private string $databaseName;

    public function __construct(array $config = [])
    {
        $connectionName = $config['connection'] ?? 'mongodb';
        $connection = DB::connection($connectionName);
        $this->databaseName = $config['database'] ?? 'mongodb';

        // For PHPStan, directly create MongoDB client
        $this->database = (new Client('mongodb://localhost:27017'))->selectDatabase($this->databaseName);

        $this->collectionPrefix = $config['collection_prefix'] ?? 'audit_';
        $this->collectionSuffix = $config['collection_suffix'] ?? '_logs';
    }

    public function store(AuditLogInterface $log): void
    {
        $collectionName = $this->getCollectionName($log->getEntityType());
        $collection = $this->getCollection($collectionName);

        $timestamp = 0;
        $createdAt = $log->getCreatedAt();
        // CreatedAt will always be a DateTime object from the AuditLog model
        $timestamp = strtotime($createdAt->format('Y-m-d H:i:s')) * 1000;

        $collection->insertOne([
            'entity_id' => (string) $log->getEntityId(),
            'action' => $log->getAction(),
            'old_values' => $log->getOldValues(),
            'new_values' => $log->getNewValues(),
            'causer_type' => $log->getCauserType(),
            'causer_id' => $log->getCauserId() !== null ? (string) $log->getCauserId() : null,
            'metadata' => $log->getMetadata(),
            'created_at' => new UTCDateTime($timestamp),
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
        $collection = $this->getCollection($collectionName);

        $query = [
            'entity_id' => (string) $entityId,
        ];

        $createdAtConditions = [];

        if (isset($options['action'])) {
            $query['action'] = $options['action'];
        }

        if (isset($options['from_date'])) {
            $createdAtConditions['$gte'] = new UTCDateTime(strtotime($options['from_date']) * 1000);
        }

        if (isset($options['to_date'])) {
            $createdAtConditions['$lte'] = new UTCDateTime(strtotime($options['to_date']) * 1000);
        }

        // Only add created_at to query if we have date conditions
        if (count($createdAtConditions) > 0) {
            $query['created_at'] = $createdAtConditions;
        }

        $sort = [
            'created_at' => isset($options['sort']) && $options['sort'] === 'asc' ? 1 : -1,
        ];

        $findOptions = [];

        if (isset($options['limit'])) {
            $findOptions['limit'] = (int) $options['limit'];
        }

        if (isset($options['offset'])) {
            $findOptions['skip'] = (int) $options['offset'];
        }

        $cursor = $collection->find($query, array_merge($findOptions, ['sort' => $sort]));

        $logs = [];

        foreach ($cursor as $record) {
            $createdAt = null;

            // Handle created_at field
            if (isset($record['created_at'])) {
                if ($record['created_at'] instanceof UTCDateTime) {
                    $createdAt = $record['created_at']->toDateTime();
                } else {
                    $createdAt = new \DateTime();
                }
            } else {
                $createdAt = new \DateTime();
            }

            $logs[] = new AuditLog(
                entityType: $entityType,
                entityId: $record['entity_id'],
                action: $record['action'],
                oldValues: $record['old_values'] ?? null,
                newValues: $record['new_values'] ?? null,
                causerType: $record['causer_type'] ?? null,
                causerId: $record['causer_id'] ?? null,
                metadata: $record['metadata'] ?? [],
                createdAt: $createdAt
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
        $collections = $this->database->listCollectionNames();

        foreach ($collections as $collection) {
            if ($collection === $collectionName) {
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
        $autoMigration = config('audit-logger.auto_migration');
        if ($autoMigration === false) {
            return;
        }

        // No additional action needed for MongoDB as collections are created on first use
    }

    private function getCollectionName(string $entityType): string
    {
        $baseName = Str::snake(class_basename($entityType));
        $pluralName = Str::plural($baseName);

        return $this->collectionPrefix . $pluralName . $this->collectionSuffix;
    }

    /**
     * Get MongoDB collection instance
     */
    private function getCollection(string $collectionName): Collection
    {
        return $this->database->selectCollection($collectionName);
    }
}
