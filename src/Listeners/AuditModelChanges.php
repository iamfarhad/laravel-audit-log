<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Listeners;

use Illuminate\Support\Carbon;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

final class AuditModelChanges
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ModelAudited $event): void
    {
        try {
            $model = $event->model;

            // Check if model has required methods from Auditable trait
            if (
                ! method_exists($model, 'getAuditableAttributes') ||
                ! method_exists($model, 'getAuditEntityType') ||
                ! method_exists($model, 'getAuditMetadata')
            ) {
                return;
            }

            // Filter attributes based on include/exclude rules
            $oldValues = $event->oldValues !== null ? $model->getAuditableAttributes($event->oldValues) : null;
            $newValues = $event->newValues !== null ? $model->getAuditableAttributes($event->newValues) : null;

            // Skip if no changes after filtering
            if ($event->action === 'updated' && ($newValues === null || count($newValues) === 0)) {
                return;
            }

            $this->auditLogger->log(
                new AuditLog(
                    entityType: $model->getAuditEntityType(),
                    entityId: $model->getKey(),
                    action: $event->action,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    metadata: $model->getAuditMetadata(),
                    causerType: app(CauserResolverInterface::class)->resolve()['type'],
                    causerId: app(CauserResolverInterface::class)->resolve()['id'],
                    createdAt: Carbon::now(),
                )
            );
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
