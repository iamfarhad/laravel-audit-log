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
        $payload = $this->sortRecursive([
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
        ]);

        $serialized = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $algorithm = (string) config('audit-logger.security.hashing.algorithm', 'sha256');

        return hash_hmac($algorithm, $serialized, $this->key());
    }

    /** @param array<mixed> $value */
    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function key(): string
    {
        $key = (string) config('audit-logger.security.hashing.key', config('app.key', ''));

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
