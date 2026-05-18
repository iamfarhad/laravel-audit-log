<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuditAnalytics
{
    /** @return array<string, mixed> */
    public function summary(string $entityClass): array
    {
        $query = EloquentAuditLog::forEntity($entityClass)->newQuery();

        return [
            'total' => (clone $query)->count(),
            'first_audit_at' => (clone $query)->min('created_at'),
            'last_audit_at' => (clone $query)->max('created_at'),
            'actions' => $this->topActions($entityClass)->pluck('count', 'action')->all(),
        ];
    }

    /** @return Collection<int, object> */
    public function topActions(string $entityClass, int $limit = 10): Collection
    {
        return EloquentAuditLog::forEntity($entityClass)
            ->newQuery()
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, object> */
    public function topCausers(string $entityClass, int $limit = 10): Collection
    {
        return EloquentAuditLog::forEntity($entityClass)
            ->newQuery()
            ->select('causer_type', 'causer_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('causer_id')
            ->groupBy('causer_type', 'causer_id')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, object> */
    public function topChangedEntities(string $entityClass, int $limit = 10): Collection
    {
        return EloquentAuditLog::forEntity($entityClass)
            ->newQuery()
            ->select('entity_id', DB::raw('COUNT(*) as count'))
            ->groupBy('entity_id')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, object> */
    public function changesPerDay(string $entityClass, int $days = 30): Collection
    {
        return EloquentAuditLog::forEntity($entityClass)
            ->newQuery()
            ->selectRaw('DATE(created_at) as date, action, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get();
    }
}
