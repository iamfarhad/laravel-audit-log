<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services\Retention;

use iamfarhad\LaravelAuditLog\Contracts\RetentionStrategyInterface;
use iamfarhad\LaravelAuditLog\DTOs\RetentionConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ArchiveRetentionStrategy implements RetentionStrategyInterface
{
    public function execute(string $entityType, RetentionConfig $config): int
    {
        if (! $this->canExecute($config)) {
            throw new \InvalidArgumentException('Archive connection not configured');
        }

        $cutoffDate = now()->subDays($config->days);
        $totalArchived = 0;

        // Ensure archive table exists
        $this->ensureArchiveTableExists($config);

        // Archive records in batches
        do {
            $records = DB::connection($this->getSourceConnection($config))
                ->table($config->tableName)
                ->where('created_at', '<', $cutoffDate)
                ->limit($config->batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            // Insert into archive
            DB::connection($config->archiveConnection)
                ->table($this->getArchiveTableName($config))
                ->insert($records->toArray());

            // Delete from source
            $recordIds = $records->pluck('id')->toArray();
            DB::connection($this->getSourceConnection($config))
                ->table($config->tableName)
                ->whereIn('id', $recordIds)
                ->delete();

            $totalArchived += $records->count();
        } while ($records->count() === $config->batchSize);

        return $totalArchived;
    }

    public function getName(): string
    {
        return 'archive';
    }

    public function canExecute(RetentionConfig $config): bool
    {
        return ! empty($config->archiveConnection) &&
            config("database.connections.{$config->archiveConnection}") !== null;
    }

    private function ensureArchiveTableExists(RetentionConfig $config): void
    {
        $archiveTableName = $this->getArchiveTableName($config);

        if (! Schema::connection($config->archiveConnection)->hasTable($archiveTableName)) {
            Schema::connection($config->archiveConnection)->create($archiveTableName, function ($table) {
                // Copy the same structure as the original audit table
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
                $table->timestamp('anonymized_at')->nullable();
                $table->timestamp('archived_at')->default(now());

                // Indexes
                $table->index('entity_id');
                $table->index('causer_id');
                $table->index('created_at');
                $table->index('action');
                $table->index('archived_at');
            });
        }
    }

    private function getArchiveTableName(RetentionConfig $config): string
    {
        return $config->tableName.'_archive';
    }

    private function getSourceConnection(RetentionConfig $config): ?string
    {
        return config('audit-logger.drivers.mysql.connection');
    }
}
