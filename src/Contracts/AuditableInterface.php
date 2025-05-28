<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

interface AuditableInterface
{
    /**
     * Get fields to exclude from audit logging.
     *
     * @return array<string>
     */
    public function getAuditExclude(): array;

    /**
     * Get fields to include in audit logging.
     *
     * @return array<string>
     */
    public function getAuditInclude(): array;

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

    /**
     * Determine if the model should queue audit events.
     */
    public function shouldQueueAudit(): bool;
}
