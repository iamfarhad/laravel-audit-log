<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

interface TenantResolverInterface
{
    /**
     * @return array{type: class-string|string|null, id: string|int|null}
     */
    public function resolve(): array;
}
