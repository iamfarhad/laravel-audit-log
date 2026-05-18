<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Carbon\Carbon;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class RelationshipAuditor
{
    public function log(Model $model, string $relation, string $action, mixed $relatedIds, array $metadata = []): void
    {
        if (! method_exists($model, 'getAuditEntityType')) {
            return;
        }

        $auditLogger = app(AuditLogger::class);
        $causer = app(CauserResolverInterface::class)->resolve();

        $auditLogger->log(new AuditLog(
            entityType: $model->getAuditEntityType(),
            entityId: $model->getKey(),
            action: $action,
            oldValues: null,
            newValues: [
                'relation' => $relation,
                'related_ids' => is_array($relatedIds) ? $relatedIds : [$relatedIds],
            ],
            metadata: array_merge($metadata, ['relation' => $relation]),
            causerType: $causer['type'],
            causerId: $causer['id'],
            createdAt: Carbon::now(),
            source: $auditLogger->getSource(),
        ));
    }

    public function attached(Model $model, string $relation, mixed $relatedIds, array $metadata = []): void
    {
        $this->log($model, $relation, $relation.'_attached', $relatedIds, $metadata);
    }

    public function detached(Model $model, string $relation, mixed $relatedIds, array $metadata = []): void
    {
        $this->log($model, $relation, $relation.'_detached', $relatedIds, $metadata);
    }

    public function synced(Model $model, string $relation, mixed $relatedIds, array $metadata = []): void
    {
        $this->log($model, $relation, $relation.'_synced', $relatedIds, $metadata);
    }
}
