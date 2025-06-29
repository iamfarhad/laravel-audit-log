<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

use iamfarhad\LaravelAuditLog\DTOs\RetentionResult;

interface RetentionServiceInterface
{
    /**
     * Run retention cleanup for all registered entities.
     */
    public function runCleanup(): RetentionResult;

    /**
     * Run retention cleanup for a specific entity.
     */
    public function runCleanupForEntity(string $entityType): RetentionResult;

    /**
     * Get the retention configuration for an entity.
     */
    public function getRetentionConfig(string $entityType): ?array;

    /**
     * Check if retention is enabled globally.
     */
    public function isRetentionEnabled(): bool;

    /**
     * Check if retention is enabled for a specific entity.
     */
    public function isRetentionEnabledForEntity(string $entityType): bool;
}
