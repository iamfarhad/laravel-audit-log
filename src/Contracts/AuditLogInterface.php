<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

use DateTimeInterface;

interface AuditLogInterface
{
    /**
     * Get the entity type being audited.
     */
    public function getEntityType(): string;

    /**
     * Get the entity ID being audited.
     */
    public function getEntityId(): string|int;

    /**
     * Get the action performed (created, updated, deleted, etc.).
     */
    public function getAction(): string;

    /**
     * Get the old values before the change.
     */
    public function getOldValues(): ?array;

    /**
     * Get the new values after the change.
     */
    public function getNewValues(): ?array;

    /**
     * Get the causer type (user model class).
     */
    public function getCauserType(): ?string;

    /**
     * Get the causer ID.
     */
    public function getCauserId(): string|int|null;

    /**
     * Get additional metadata.
     */
    public function getMetadata(): array;

    /**
     * Get the timestamp of the audit log.
     */
    public function getCreatedAt(): DateTimeInterface;

    /**
     * Convert to array representation.
     */
    public function toArray(): array;
}
