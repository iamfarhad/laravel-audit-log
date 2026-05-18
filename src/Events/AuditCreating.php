<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Events;

use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;

final class AuditCreating
{
    public function __construct(public readonly AuditLogInterface $auditLog) {}
}
