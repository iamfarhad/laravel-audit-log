<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Models;

use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class AuditLog implements AuditLogInterface
{
    public function __construct(
        private readonly string $entityType,
        private readonly string|int $entityId,
        private readonly string $action,
        private readonly ?array $oldValues,
        private readonly ?array $newValues,
        private readonly ?string $causerType,
        private readonly string|int|null $causerId,
        private readonly array $metadata,
        private readonly DateTimeInterface $createdAt
    ) {}

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string|int
    {
        return $this->entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function getCauserType(): ?string
    {
        return $this->causerType;
    }

    public function getCauserId(): string|int|null
    {
        return $this->causerId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'causer_type' => $this->causerType,
            'causer_id' => $this->causerId,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt instanceof Carbon
                ? $this->createdAt->toIso8601String()
                : $this->createdAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Create a new AuditLog instance from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entityType: $data['entity_type'],
            entityId: $data['entity_id'],
            action: $data['action'],
            oldValues: $data['old_values'] ?? null,
            newValues: $data['new_values'] ?? null,
            causerType: $data['causer_type'] ?? null,
            causerId: $data['causer_id'] ?? null,
            metadata: $data['metadata'] ?? [],
            createdAt: $data['created_at'] instanceof DateTimeInterface
                ? $data['created_at']
                : Carbon::parse($data['created_at'])
        );
    }
}
