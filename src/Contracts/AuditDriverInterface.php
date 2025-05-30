<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

interface AuditDriverInterface
{
    /**
     * Store a single audit log entry.
     */
    public function store(AuditLogInterface $log): void;

    /**
     * Create storage for a new entity type if needed.
     */
    public function createStorageForEntity(string $entityClass): void;

    /**
     * Check if storage exists for an entity type.
     */
    public function storageExistsForEntity(string $entityClass): bool;

    /**
     * Ensures the audit storage exists for the entity if auto_migration is enabled.
     * If it doesn't exist and auto_migration is enabled, creates it.
     */
    public function ensureStorageExists(string $entityClass): void;
}
