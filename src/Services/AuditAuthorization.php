<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class AuditAuthorization
{
    public function canView(Model $model): bool
    {
        return $this->allows((string) config('audit-logger.authorization.view_gate', 'viewAuditLogs'), $model);
    }

    public function canRestore(Model $model): bool
    {
        return $this->allows((string) config('audit-logger.authorization.restore_gate', 'restoreFromAudit'), $model);
    }

    private function allows(string $gate, Model $model): bool
    {
        if (! (bool) config('audit-logger.authorization.enabled', false)) {
            return true;
        }

        return Gate::allows($gate, $model);
    }
}
