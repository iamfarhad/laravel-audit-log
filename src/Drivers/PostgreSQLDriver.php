<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Drivers;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\TenantResolverInterface;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditChangeSet;
use iamfarhad\LaravelAuditLog\Services\AuditHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PostgreSQLDriver implements AuditDriverInterface
{
    private string $tablePrefix;

    private string $tableSuffix;

    private array $config;

    private string $connection;

    private static array $existingTables = [];

    public function __construct(?string $connection = null)
    {
        $this->config = config('audit-logger');
        $this->connection = $connection ?? $this->config['drivers']['postgresql']['connection'] ?? config('database.default');
        $this->tablePrefix = $this->config['drivers']['postgresql']['table_prefix'] ?? 'audit_';
        $this->tableSuffix = $this->config['drivers']['postgresql']['table_suffix'] ?? '_logs';
    }

    private function validateEntityType(string $entityType): void
    {
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
        $this->ensureStorageExists($log->getEntityType());
        $model = EloquentAuditLog::forEntity(entityClass: $log->getEntityType());
        $model->setConnection($this->connection);
        $model->fill($this->payloadForLog($log));
        $model->save();
    }

    /** @param array<AuditLogInterface> $logs */
    public function storeBatch(array $logs): void
    {
        $grouped = [];

        foreach ($logs as $log) {
            $this->validateEntityType($log->getEntityType());
            $this->ensureStorageExists($log->getEntityType());
            $grouped[$this->getTableName($log->getEntityType())][] = $this->payloadForInsert($log);
        }

        foreach ($grouped as $tableName => $rows) {
            foreach (array_chunk($rows, (int) config('audit-logger.batch.size', 500)) as $chunk) {
                DB::connection($this->connection)->table($tableName)->insert($chunk);
            }
        }
    }

    public function createStorageForEntity(string $entityClass): void
    {
        $this->validateEntityType($entityClass);
        $tableName = $this->getTableName($entityClass);
        Schema::connection($this->connection)->create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('entity_id');
            $table->string('action');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('changes')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->string('tenant_type')->nullable();
            $table->string('tenant_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');
            $table->string('source')->nullable();
            $table->string('audit_hash', 128)->nullable();
            $table->string('previous_hash', 128)->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->index('entity_id');
            $table->index('causer_id');
            $table->index('tenant_id');
            $table->index('created_at');
            $table->index('action');
            $table->index('source');
            $table->index('audit_hash');
            $table->index('previous_hash');
            $table->index('anonymized_at');
            $table->index(['entity_id', 'action']);
            $table->index(['entity_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['causer_id', 'action']);
            $table->index(['action', 'created_at']);
        });
        self::$existingTables[$tableName] = true;
    }

    public function storageExistsForEntity(string $entityClass): bool
    {
        $tableName = $this->getTableName($entityClass);
        if (isset(self::$existingTables[$tableName])) {
            return self::$existingTables[$tableName];
        }

        return self::$existingTables[$tableName] = Schema::connection($this->connection)->hasTable($tableName);
    }

    public function ensureStorageExists(string $entityClass): void
    {
        if (($this->config['auto_migration'] ?? true) === false) {
            return;
        }
        if (! $this->storageExistsForEntity($entityClass)) {
            $this->createStorageForEntity($entityClass);
        }
    }

    public static function clearCache(): void
    {
        self::$existingTables = [];
    }

    public static function clearTableCache(): void
    {
        self::$existingTables = [];
    }

    private function payloadForLog(AuditLogInterface $log): array
    {
        $payload = [
            'entity_id' => $log->getEntityId(),
            'action' => $log->getAction(),
            'old_values' => $log->getOldValues(),
            'new_values' => $log->getNewValues(),
            'causer_type' => $log->getCauserType(),
            'causer_id' => $log->getCauserId(),
            'metadata' => $log->getMetadata(),
            'created_at' => $log->getCreatedAt(),
            'source' => $log->getSource(),
        ];

        $payload = $this->withV2Context($log, $payload);

        $hash = app(AuditHash::class);
        if ($hash->enabled()) {
            $previousHash = $this->latestHashForEntity($log->getEntityType());
            $payload['previous_hash'] = $previousHash;
            $payload['audit_hash'] = $hash->compute($log, $previousHash);
        }

        return $this->filterPayloadForTable($this->getTableName($log->getEntityType()), $payload);
    }

    private function payloadForInsert(AuditLogInterface $log): array
    {
        return array_map(
            fn (mixed $value): mixed => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value,
            $this->payloadForLog($log)
        );
    }

    private function withV2Context(AuditLogInterface $log, array $payload): array
    {
        if ((bool) config('audit-logger.tenant.enabled', false)) {
            $tenant = app(TenantResolverInterface::class)->resolve();
            $payload[config('audit-logger.tenant.columns.type', 'tenant_type')] = $tenant['type'];
            $payload[config('audit-logger.tenant.columns.id', 'tenant_id')] = $tenant['id'];
        }

        if ((bool) config('audit-logger.changes.store', false)) {
            $payload[(string) config('audit-logger.changes.column', 'changes')] = app(AuditChangeSet::class)->make($log->getOldValues(), $log->getNewValues());
        }

        return $payload;
    }

    private function filterPayloadForTable(string $tableName, array $payload): array
    {
        foreach (array_keys($payload) as $column) {
            if (! Schema::connection($this->connection)->hasColumn($tableName, $column)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }

    private function latestHashForEntity(string $entityType): ?string
    {
        $value = DB::connection($this->connection)
            ->table($this->getTableName($entityType))
            ->whereNotNull('audit_hash')
            ->orderByDesc('id')
            ->value('audit_hash');

        return is_string($value) ? $value : null;
    }

    private function getTableName(string $entityType): string
    {
        $entityConfig = $this->config['entities'][$entityType] ?? [];
        $configuredTable = $entityConfig['audit_table'] ?? $entityConfig['table'] ?? null;

        if (is_string($configuredTable) && $configuredTable !== '') {
            return $configuredTable;
        }

        $tableName = Str::plural(Str::snake(class_basename($entityType)));
        if (! str_starts_with($tableName, $this->tablePrefix)) {
            $tableName = "{$this->tablePrefix}{$tableName}";
        }
        if (! str_ends_with($tableName, $this->tableSuffix)) {
            $tableName = "{$tableName}{$this->tableSuffix}";
        }

        return $tableName;
    }
}
