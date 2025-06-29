<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

use iamfarhad\LaravelAuditLog\DTOs\RetentionConfig;

interface RetentionStrategyInterface
{
    /**
     * Execute the retention strategy for the given entity.
     *
     * @param  string  $entityType  The fully qualified class name of the entity
     * @param  RetentionConfig  $config  The retention configuration
     * @return int The number of records processed
     */
    public function execute(string $entityType, RetentionConfig $config): int;

    /**
     * Get the name of this retention strategy.
     */
    public function getName(): string;

    /**
     * Check if this strategy can be executed (e.g., archive connection exists).
     */
    public function canExecute(RetentionConfig $config): bool;
}
