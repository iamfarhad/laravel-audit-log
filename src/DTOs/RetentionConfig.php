<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\DTOs;

final readonly class RetentionConfig
{
    public function __construct(
        public bool $enabled,
        public int $days,
        public string $strategy,
        public int $batchSize,
        public int $anonymizeAfterDays,
        public ?string $archiveConnection,
        public string $entityType,
        public string $tableName,
    ) {}

    public static function fromArray(array $config, string $entityType, string $tableName): self
    {
        return new self(
            enabled: $config['enabled'] ?? false,
            days: $config['days'] ?? 365,
            strategy: $config['strategy'] ?? 'delete',
            batchSize: $config['batch_size'] ?? 1000,
            anonymizeAfterDays: $config['anonymize_after_days'] ?? 180,
            archiveConnection: $config['archive_connection'] ?? null,
            entityType: $entityType,
            tableName: $tableName,
        );
    }

    public function isDeleteStrategy(): bool
    {
        return $this->strategy === 'delete';
    }

    public function isArchiveStrategy(): bool
    {
        return $this->strategy === 'archive';
    }

    public function isAnonymizeStrategy(): bool
    {
        return $this->strategy === 'anonymize';
    }

    public function shouldAnonymizeFirst(): bool
    {
        return $this->anonymizeAfterDays > 0 && $this->anonymizeAfterDays < $this->days;
    }
}
