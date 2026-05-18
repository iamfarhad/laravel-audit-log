<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\TenantResolverInterface;

final class TenantResolver implements TenantResolverInterface
{
    /** @return array{type: class-string|string|null, id: string|int|null} */
    public function resolve(): array
    {
        $resolver = config('audit-logger.tenant.resolver');

        if (is_string($resolver) && $resolver !== static::class && app()->bound($resolver)) {
            return app($resolver)->resolve();
        }

        $tenant = null;

        if (function_exists('tenant')) {
            $tenant = tenant();
        }

        if ($tenant === null && app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
        }

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return ['type' => $tenant::class, 'id' => $tenant->getKey()];
        }

        return ['type' => null, 'id' => null];
    }
}
