<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;

final class AuditHash
{
    public function enabled(): bool
    {
        return (bool) config('audit-logger.security.hashing.enabled', false);
    }

    public function compute(AuditLogInterface $log, ?string $previousHash): string
    {
        $payload = [
            'previous_hash' => $previousHash,
            'entity_type' => $log->getEntityType(),
            'entity_id' => (string) $log->getEntityId(),
            'action' => $log->getAction(),
            'old_values' => $log->getOldValues(),
            'new_values' => $log->getNewValues(),
            'causer_type' => $log->getCauserType(),
            'causer_id' => $log->getCauserId() === null ? null : (string) $log->getCauserId(),
            'metadata' => $log->getMetadata(),
            'source' => $log->getSource(),
            'created_at' => $log->getCreatedAt()->format(DATE_ATOM),
        ];

        $serialized = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $algorithm = (string) config('audit-logger.security.hashing.algorithm', 'sha256');
        $key = (string) config('audit-logger.security.hashing.key', config('app.key', ''));

        return hash_hmac($algorithm, $serialized, $key);
    }
}
