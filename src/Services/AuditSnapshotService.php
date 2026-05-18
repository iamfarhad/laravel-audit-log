<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Carbon\Carbon;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditSnapshotService
{
    public function maybeSnapshot(Model $model): void
    {
        if (! (bool) config('audit-logger.snapshots.enabled', false) || ! method_exists($model, 'getAuditEntityType')) {
            return;
        }

        $every = max(1, (int) config('audit-logger.snapshots.every', 20));
        $count = \iamfarhad\LaravelAuditLog\Models\EloquentAuditLog::forEntity($model::class)
            ->newQuery()
            ->where('entity_id', (string) $model->getKey())
            ->count();

        if ($count === 0 || $count % $every !== 0) {
            return;
        }

        $this->snapshot($model);
    }

    public function snapshot(Model $model): void
    {
        if (! method_exists($model, 'getAuditEntityType')) {
            return;
        }

        $auditLogger = app(AuditLogger::class);
        $causer = app(CauserResolverInterface::class)->resolve();

        $auditLogger->log(new AuditLog(
            entityType: $model->getAuditEntityType(),
            entityId: $model->getKey(),
            action: 'snapshot',
            oldValues: null,
            newValues: $model->getAttributes(),
            metadata: ['snapshot' => true],
            causerType: $causer['type'],
            causerId: $causer['id'],
            createdAt: Carbon::now(),
            source: $auditLogger->getSource(),
        ));
    }
}
