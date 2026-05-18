<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Events;

final class AuditVerificationFailed
{
    /** @param array<int, array<string, mixed>> $failures */
    public function __construct(
        public readonly string $entityClass,
        public readonly array $failures
    ) {}
}
