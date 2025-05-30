<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Facades;

use Illuminate\Support\Facades\Facade;

final class AuditLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'audit-logger';
    }
}
