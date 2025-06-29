<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services\Retention;

use iamfarhad\LaravelAuditLog\Contracts\RetentionStrategyInterface;
use iamfarhad\LaravelAuditLog\DTOs\RetentionConfig;
use Illuminate\Support\Facades\DB;

final class AnonymizeRetentionStrategy implements RetentionStrategyInterface
{
    private const ANONYMIZED_VALUE = '[ANONYMIZED]';

    private const SENSITIVE_FIELDS = [
        'email',
        'phone',
        'address',
        'ip_address',
        'user_agent',
        'name',
        'first_name',
        'last_name',
        'full_name',
    ];

    public function execute(string $entityType, RetentionConfig $config): int
    {
        $cutoffDate = now()->subDays($config->days);
        $totalAnonymized = 0;

        // Process records in batches
        do {
            $records = DB::connection($this->getConnection($config))
                ->table($config->tableName)
                ->where('created_at', '<', $cutoffDate)
                ->whereNull('anonymized_at') // Only anonymize non-anonymized records
                ->limit($config->batchSize)
                ->get(['id', 'old_values', 'new_values', 'metadata']);

            if ($records->isEmpty()) {
                break;
            }

            foreach ($records as $record) {
                $anonymizedOldValues = $this->anonymizeData($record->old_values);
                $anonymizedNewValues = $this->anonymizeData($record->new_values);
                $anonymizedMetadata = $this->anonymizeData($record->metadata);

                DB::connection($this->getConnection($config))
                    ->table($config->tableName)
                    ->where('id', $record->id)
                    ->update([
                        'old_values' => $anonymizedOldValues,
                        'new_values' => $anonymizedNewValues,
                        'metadata' => $anonymizedMetadata,
                        'causer_type' => null,
                        'causer_id' => null,
                        'anonymized_at' => now(),
                    ]);
            }

            $totalAnonymized += $records->count();
        } while ($records->count() === $config->batchSize);

        return $totalAnonymized;
    }

    public function getName(): string
    {
        return 'anonymize';
    }

    public function canExecute(RetentionConfig $config): bool
    {
        return true; // Anonymize strategy can always execute
    }

    /**
     * Anonymize sensitive data in the given array.
     */
    private function anonymizeData(?string $jsonData): ?string
    {
        if ($jsonData === null || $jsonData === '') {
            return $jsonData;
        }

        $data = json_decode($jsonData, true);
        if (! is_array($data)) {
            return $jsonData;
        }

        $anonymized = $this->anonymizeArray($data);

        return json_encode($anonymized);
    }

    /**
     * Recursively anonymize sensitive fields in an array.
     */
    private function anonymizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->anonymizeArray($value);
            } elseif ($this->isSensitiveField($key)) {
                $data[$key] = self::ANONYMIZED_VALUE;
            }
        }

        return $data;
    }

    /**
     * Check if a field is considered sensitive and should be anonymized.
     */
    private function isSensitiveField(string $fieldName): bool
    {
        $fieldName = strtolower($fieldName);

        foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
            if (str_contains($fieldName, $sensitiveField)) {
                return true;
            }
        }

        return false;
    }

    private function getConnection(RetentionConfig $config): ?string
    {
        return config('audit-logger.drivers.mysql.connection');
    }
}
