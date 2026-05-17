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
        $log = $this->resolveLog($model, $auditLog);
        $values = $this->valuesForMode($log, $mode);

        if ($values === []) {
            throw new InvalidArgumentException("The selected audit log does not contain {$mode} values to apply.");
        }

        $model->forceFill($values);

        if ($save) {
            $model->save();
        }

        return $model;
    }

    private function resolveLog(Model $model, EloquentAuditLog|int|string $auditLog): EloquentAuditLog
    {
        if ($auditLog instanceof EloquentAuditLog) {
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
}
