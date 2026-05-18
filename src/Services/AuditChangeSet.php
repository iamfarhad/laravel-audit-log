<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

final class AuditChangeSet
{
    /**
     * @return array<int, array{field: string, old: mixed, new: mixed, type: string, label: string}>
     */
    public function make(?array $oldValues, ?array $newValues): array
    {
        $oldValues ??= [];
        $newValues ??= [];
        $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        $changes = [];

        foreach ($fields as $field) {
            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;

            if ($old === $new) {
                continue;
            }

            $changes[] = [
                'field' => (string) $field,
                'old' => $old,
                'new' => $new,
                'type' => $this->typeFor($new ?? $old),
                'label' => str((string) $field)->replace('_', ' ')->title()->toString(),
            ];
        }

        return $changes;
    }

    private function typeFor(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'array',
            $value === null => 'null',
            default => 'string',
        };
    }
}
