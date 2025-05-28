<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void log(string $entityType, string|int $entityId, string $action, ?array $oldValues = null, ?array $newValues = null, array $metadata = [])
 * @method static void flush()
 * @method static array getLogsForEntity(string $entityType, string|int $entityId, array $options = [])
 * @method static void createStorageForEntity(string $entityClass)
 * @method static bool storageExistsForEntity(string $entityClass)
 * @method static void ensureStorageExists(string $entityClass)
 * @method static \iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface driver(?string $name = null)
 *
 * @see \iamfarhad\LaravelAuditLog\Services\AuditLogger
 */
final class AuditLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'audit-logger';
    }
}
