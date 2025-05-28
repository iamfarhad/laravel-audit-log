<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

interface AuditableInterface
{
    /**
     * Determine if auditing is enabled for this model.
     */
    public function isAuditingEnabled(): bool;

    /**
     * Get the entity type for audit logging.
     */
    public function getAuditEntityType(): string;

    /**
     * Get custom metadata for audit logs.
     */
    public function getAuditMetadata(): array;

    /**
     * Get the auditable attributes after applying include/exclude rules.
     */
    public function getAuditableAttributes(array $attributes): array;

    /**
     * Get the primary key value for the model.
     */
    public function getKey(): string|int;
}
