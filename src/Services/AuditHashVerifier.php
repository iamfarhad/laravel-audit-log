<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class AuditHashVerifier
{
    public function __construct(private readonly AuditHash $hash) {}

    /** @return array{valid: bool, checked: int, failures: array<int, array<string, mixed>>} */
    public function verify(string $entityClass, string|int|null $entityId = null): array
    {
        $model = EloquentAuditLog::forEntity($entityClass);
        $connection = $model->getConnectionName();
        $table = $model->getTable();

        if (! Schema::connection($connection)->hasTable($table)) {
            return $this->failure('missing_audit_table', "Audit table [{$table}] does not exist.");
        }

        if (! Schema::connection($connection)->hasColumn($table, 'audit_hash') || ! Schema::connection($connection)->hasColumn($table, 'previous_hash')) {
            return $this->failure(
                'missing_hash_columns',
                "Audit table [{$table}] must have nullable [audit_hash] and [previous_hash] columns before hash-chain verification can run."
            );
        }

        $previousHash = null;
        $failures = [];
        $checked = 0;

        try {
            $query = $model->newQuery()->orderBy('id');

            if ($entityId !== null) {
                $query->where('entity_id', (string) $entityId);
            }

            foreach ($query->lazy() as $log) {
                $checked++;
                $expected = $this->hash->compute(AuditLog::fromArray([
                    'entity_type' => $entityClass,
                    'entity_id' => $log->entity_id,
                    'action' => $log->action,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'causer_type' => $log->causer_type,
                    'causer_id' => $log->causer_id,
                    'metadata' => $log->metadata ?? [],
                    'created_at' => $log->created_at,
                    'source' => $log->source,
                ]), $previousHash);

                if (($log->previous_hash ?? null) !== $previousHash || ($log->audit_hash ?? null) !== $expected) {
                    $failures[] = [
                        'code' => 'hash_mismatch',
                        'message' => 'The stored audit hash or previous hash does not match the expected value.',
                        'id' => $log->getKey(),
                        'expected_previous_hash' => $previousHash,
                        'actual_previous_hash' => $log->previous_hash ?? null,
                        'expected_audit_hash' => $expected,
                        'actual_audit_hash' => $log->audit_hash ?? null,
                    ];
                }

                $previousHash = $log->audit_hash ?? null;
            }
        } catch (Throwable $exception) {
            return $this->failure('verification_query_failed', $exception->getMessage());
        }

        return ['valid' => $failures === [], 'checked' => $checked, 'failures' => $failures];
    }

    /** @return array{valid: false, checked: 0, failures: array<int, array<string, string>>} */
    private function failure(string $code, string $message): array
    {
        return [
            'valid' => false,
            'checked' => 0,
            'failures' => [
                ['code' => $code, 'message' => $message],
            ],
        ];
    }
}
