<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class AuditTimeline
{
    /** @return Collection<int, array<string, mixed>> */
    public function forModel(Model $model): Collection
    {
        return EloquentAuditLog::forEntity($model::class)
            ->newQuery()
            ->where('entity_id', (string) $model->getKey())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (EloquentAuditLog $log): array => $this->entry($log));
    }

    /** @return array<string, mixed> */
    public function entry(EloquentAuditLog $log): array
    {
        return [
            'id' => $log->getKey(),
            'action' => $log->action,
            'entity_id' => $log->entity_id,
            'causer_type' => $log->causer_type,
            'causer_id' => $log->causer_id,
            'source' => $log->source,
            'created_at' => $log->created_at,
            'changes' => $this->diff($log),
            'metadata' => $log->metadata ?? [],
            'audit_hash' => $log->audit_hash ?? null,
            'previous_hash' => $log->previous_hash ?? null,
        ];
    }

    /** @return array<int, array{field: string, old: mixed, new: mixed}> */
    public function diff(EloquentAuditLog $log): array
    {
        $oldValues = is_array($log->old_values) ? $log->old_values : [];
        $newValues = is_array($log->new_values) ? $log->new_values : [];
        $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        $diff = [];

        foreach ($fields as $field) {
            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;
            if ($old !== $new) {
                $diff[] = ['field' => (string) $field, 'old' => $old, 'new' => $new];
            }
        }

        return $diff;
    }
}
