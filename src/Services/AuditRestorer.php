<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AuditRestorer
{
    public function restore(Model $model, EloquentAuditLog|int|string $auditLog, bool $save = true): Model
    {
        return $this->apply($model, $auditLog, 'new', $save);
    }

    public function rollback(Model $model, EloquentAuditLog|int|string $auditLog, bool $save = true): Model
    {
        return $this->apply($model, $auditLog, 'old', $save);
    }

    /** @return array<int, array{field: string, current: mixed, target: mixed}> */
    public function preview(Model $model, EloquentAuditLog|int|string $auditLog, string $mode = 'new'): array
    {
        $log = $this->resolveLog($model, $auditLog);
        $values = $this->valuesForMode($log, $mode);
        $changes = [];

        foreach ($values as $field => $targetValue) {
            $currentValue = $model->getAttribute((string) $field);
            if ($currentValue !== $targetValue) {
                $changes[] = ['field' => (string) $field, 'current' => $currentValue, 'target' => $targetValue];
            }
        }

        return $changes;
    }

    private function apply(Model $model, EloquentAuditLog|int|string $auditLog, string $mode, bool $save): Model
    {
        if (! app(AuditAuthorization::class)->canRestore($model)) {
            throw new InvalidArgumentException('You are not authorized to restore this model from audit history.');
        }

        $log = $this->resolveLog($model, $auditLog);
        $values = $this->valuesForMode($log, $mode);
        $values = $this->filterRestorableValues($model, $values);

        if ($values === []) {
            throw new InvalidArgumentException("The selected audit log does not contain {$mode} values to apply.");
        }

        $model->forceFill($values);

        if ($save) {
            $this->saveModel($model);
        }

        return $model;
    }

    private function resolveLog(Model $model, EloquentAuditLog|int|string $auditLog): EloquentAuditLog
    {
        if ($auditLog instanceof EloquentAuditLog) {
            $this->ensureLogBelongsToModel($model, $auditLog);

            return $auditLog;
        }

        /** @var EloquentAuditLog|null $log */
        $log = EloquentAuditLog::forEntity($model::class)
            ->newQuery()
            ->where('entity_id', (string) $model->getKey())
            ->whereKey($auditLog)
            ->first();

        if (! $log instanceof EloquentAuditLog) {
            throw new InvalidArgumentException('Audit log not found for this model.');
        }

        return $log;
    }

    private function ensureLogBelongsToModel(Model $model, EloquentAuditLog $log): void
    {
        $expectedTable = EloquentAuditLog::forEntity($model::class)->getTable();

        if ($log->getTable() !== $expectedTable || (string) $log->entity_id !== (string) $model->getKey()) {
            throw new InvalidArgumentException('Audit log not found for this model.');
        }
    }

    /** @return array<string, mixed> */
    private function valuesForMode(EloquentAuditLog $log, string $mode): array
    {
        $values = match ($mode) {
            'new', 'restore', 'replay' => $log->new_values,
            'old', 'rollback' => $log->old_values,
            default => throw new InvalidArgumentException('Restore mode must be new, old, restore, replay, or rollback.'),
        };

        return is_array($values) ? $values : [];
    }

    /** @param array<string, mixed> $values */
    private function filterRestorableValues(Model $model, array $values): array
    {
        if (! (bool) config('audit-logger.restore.validate_fillable', false)) {
            return $values;
        }

        return array_intersect_key($values, array_flip($model->getFillable()));
    }

    private function saveModel(Model $model): void
    {
        if ((bool) config('audit-logger.restore.audit_restores', true)) {
            $model->save();

            return;
        }

        $wasAuditingEnabled = method_exists($model, 'isAuditingEnabled') ? $model->isAuditingEnabled() : null;

        if (method_exists($model, 'disableAuditing')) {
            $model->disableAuditing();
        }

        try {
            $model->save();
        } finally {
            if ($wasAuditingEnabled === true && method_exists($model, 'enableAuditing')) {
                $model->enableAuditing();
            }
        }
    }
}
