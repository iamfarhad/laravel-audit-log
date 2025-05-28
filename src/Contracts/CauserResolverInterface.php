<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

interface CauserResolverInterface
{
    /**
     * Resolve the current causer of the audit log.
     *
     * @return array{type: string|null, id: string|int|null}
     */
    public function resolve(): array;
}
