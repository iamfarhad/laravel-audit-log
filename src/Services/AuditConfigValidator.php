<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use iamfarhad\LaravelAuditLog\Contracts\TenantResolverInterface;

final class AuditConfigValidator
{
    /** @return array{errors: array<int, string>, warnings: array<int, string>} */
    public function validate(): array
    {
        $errors = [];
        $warnings = [];
        $driver = (string) config('audit-logger.default', 'mysql');

        if (! in_array($driver, ['mysql', 'postgresql', 'pgsql'], true)) {
            $errors[] = "Unsupported audit driver [{$driver}].";
        }

        if ((bool) config('audit-logger.security.hashing.enabled', false) && blank(config('audit-logger.security.hashing.key'))) {
            $errors[] = 'Audit hash-chain is enabled but no AUDIT_HASH_KEY or APP_KEY is configured.';
        }

        $tenantResolver = config('audit-logger.tenant.resolver');
        if ((bool) config('audit-logger.tenant.enabled', false) && is_string($tenantResolver)) {
            if (! class_exists($tenantResolver)) {
                $errors[] = "Tenant resolver [{$tenantResolver}] does not exist.";
            } elseif (! is_subclass_of($tenantResolver, TenantResolverInterface::class)) {
                $errors[] = "Tenant resolver [{$tenantResolver}] must implement ".TenantResolverInterface::class.'.';
            }
        }

        foreach ((array) config('audit-logger.fields.transformers', []) as $field => $transformer) {
            if (! is_string($transformer) || ! class_exists($transformer)) {
                $errors[] = "Transformer for field [{$field}] does not exist.";
                continue;
            }

            if (! is_subclass_of($transformer, FieldTransformerInterface::class)) {
                $errors[] = "Transformer [{$transformer}] must implement ".FieldTransformerInterface::class.'.';
            }
        }

        if ((bool) config('audit-logger.security.append_only', false) && ! (bool) config('audit-logger.security.hashing.enabled', false)) {
            $warnings[] = 'Append-only mode is enabled without hash-chain verification.';
        }

        if ((bool) config('audit-logger.auto_migration', true)) {
            $warnings[] = 'Auto-migration is enabled. For production, prefer generated migrations.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
