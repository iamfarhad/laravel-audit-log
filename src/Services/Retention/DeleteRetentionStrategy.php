<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services\Retention;

use iamfarhad\LaravelAuditLog\Contracts\RetentionStrategyInterface;
use iamfarhad\LaravelAuditLog\DTOs\RetentionConfig;
use Illuminate\Support\Facades\DB;

final class DeleteRetentionStrategy implements RetentionStrategyInterface
{
    public function execute(string $entityType, RetentionConfig $config): int
    {
        $cutoffDate = now()->subDays($config->days);
        $totalDeleted = 0;

        // First handle anonymization if needed
        if ($config->shouldAnonymizeFirst()) {
            $anonymizeDate = now()->subDays($config->anonymizeAfterDays);
            $anonymizeStrategy = new AnonymizeRetentionStrategy;

            // Create a temporary config for anonymization
            $anonymizeConfig = new RetentionConfig(
                enabled: true,
                days: $config->anonymizeAfterDays,
                strategy: 'anonymize',
                batchSize: $config->batchSize,
                anonymizeAfterDays: $config->anonymizeAfterDays,
                archiveConnection: $config->archiveConnection,
                entityType: $config->entityType,
                tableName: $config->tableName,
            );

            $anonymizeStrategy->execute($entityType, $anonymizeConfig);
        }

        // Delete records in batches
        do {
            $deleted = DB::connection($this->getConnection($config))
                ->table($config->tableName)
                ->where('created_at', '<', $cutoffDate)
                ->limit($config->batchSize)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted > 0);

        return $totalDeleted;
    }

    public function getName(): string
    {
        return 'delete';
    }

    public function canExecute(RetentionConfig $config): bool
    {
        return true; // Delete strategy can always execute
    }

    private function getConnection(RetentionConfig $config): ?string
    {
        return config('audit-logger.drivers.mysql.connection');
    }
}
