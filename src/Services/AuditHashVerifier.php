<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

final class AuditHashVerifier
{
    public function __construct(private readonly AuditHash $hash) {}

    /** @return array{valid: bool, checked: int, failures: array<int, array<string, mixed>>} */
    public function verify(string $entityClass, string|int|null $entityId = null): array
    {
        $query = EloquentAuditLog::forEntity($entityClass)->newQuery()->orderBy('id');

        if ($entityId !== null) {
            $query->where('entity_id', (string) $entityId);
        }

        $logs = $query->get();
        $previousHash = null;
        $failures = [];

        foreach ($logs as $log) {
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
                    'id' => $log->getKey(),
                    'expected_previous_hash' => $previousHash,
                    'actual_previous_hash' => $log->previous_hash ?? null,
                    'expected_audit_hash' => $expected,
                    'actual_audit_hash' => $log->audit_hash ?? null,
                ];
            }

            $previousHash = $log->audit_hash ?? null;
        }

        return ['valid' => $failures === [], 'checked' => $logs->count(), 'failures' => $failures];
    }
}
