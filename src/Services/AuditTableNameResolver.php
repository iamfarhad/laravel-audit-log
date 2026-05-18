<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Support\Str;

final class AuditTableNameResolver
{
    public function resolve(string $entityClass): string
    {
        $config = config('audit-logger');
        $driver = $config['default'] ?? 'mysql';
        $driverConfig = $config['drivers'][$driver] ?? $config['drivers']['mysql'] ?? [];
        $entityConfig = $config['entities'][$entityClass] ?? [];
        $configuredTable = $entityConfig['audit_table'] ?? $entityConfig['table'] ?? null;

        if (is_string($configuredTable) && $configuredTable !== '') {
            return $configuredTable;
        }

        return ($driverConfig['table_prefix'] ?? 'audit_')
            .Str::plural(Str::snake(class_basename($entityClass)))
            .($driverConfig['table_suffix'] ?? '_logs');
    }
}
