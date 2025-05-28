<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Listeners;

use Illuminate\Support\Facades\Log;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;

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
        Log::debug('AuditModelChanges listener triggered', [
            'model' => get_class($event->model),
            'action' => $event->action,
        ]);

        try {
            $model = $event->model;

            // Check if model has required methods from Auditable trait
            if (
                ! method_exists($model, 'getAuditableAttributes') ||
                ! method_exists($model, 'getAuditEntityType') ||
                ! method_exists($model, 'getAuditMetadata')
            ) {
                Log::warning('Model missing required audit methods', ['model' => get_class($model)]);

                return;
            }

            // Filter attributes based on include/exclude rules
            $oldValues = $event->oldValues !== null ? $model->getAuditableAttributes($event->oldValues) : null;
            $newValues = $event->newValues !== null ? $model->getAuditableAttributes($event->newValues) : null;

            // Skip if no changes after filtering
            if ($event->action === 'updated' && ($newValues === null || count($newValues) === 0)) {
                Log::debug('No changes after filtering, skipping audit log');

                return;
            }

            // Log the audit
            Log::debug('Calling AuditLogger to log audit event', [
                'entity_type' => $model->getAuditEntityType(),
                'entity_id' => $model->getKey(),
            ]);
            $this->auditLogger->log(
                entityType: $model->getAuditEntityType(),
                entityId: $model->getKey(),
                action: $event->action,
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: $model->getAuditMetadata()
            );

            Log::debug('Audit log stored', [
                'entity' => $model->getAuditEntityType(),
                'id' => $model->getKey(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit log failed', [
                'error' => $e->getMessage(),
                'model' => $model->getKey() ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
