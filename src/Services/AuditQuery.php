<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AuditQuery
{
    private Builder $query;

    public function __construct(private readonly string $entityClass)
    {
        $this->query = EloquentAuditLog::forEntity($entityClass)->newQuery();
    }

    public function forEntityId(string|int $entityId): self
    {
        $this->query->where('entity_id', (string) $entityId);

        return $this;
    }

    public function forAction(string|array $action): self
    {
        is_array($action) ? $this->query->whereIn('action', $action) : $this->query->where('action', $action);

        return $this;
    }

    public function forCauser(string $causerType, string|int|null $causerId = null): self
    {
        $this->query->where('causer_type', $causerType);
        if ($causerId !== null) {
            $this->query->where('causer_id', (string) $causerId);
        }

        return $this;
    }

    public function between(mixed $from, mixed $to): self
    {
        $this->query->whereBetween('created_at', [$from, $to]);

        return $this;
    }

    public function search(string $term): self
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $jsonColumns = ['old_values', 'new_values', 'metadata'];
        $connection = $this->query->getModel()->getConnection();
        $grammar = $connection->getQueryGrammar();
        $isPostgreSQL = $connection->getDriverName() === 'pgsql';

        $this->query->where(function (Builder $query) use ($grammar, $isPostgreSQL, $jsonColumns, $like): void {
            $query->where('entity_id', 'like', $like)
                ->orWhere('action', 'like', $like)
                ->orWhere('source', 'like', $like)
                ->orWhere('causer_type', 'like', $like)
                ->orWhere('causer_id', 'like', $like);

            foreach ($jsonColumns as $column) {
                if ($isPostgreSQL) {
                    $query->orWhereRaw($grammar->wrap($column).'::text LIKE ?', [$like]);
                } else {
                    $query->orWhere($column, 'like', $like);
                }
            }
        });

        return $this;
    }

    public function latest(): self
    {
        $this->query->orderByDesc('created_at')->orderByDesc('id');

        return $this;
    }

    /** @return Collection<int, EloquentAuditLog> */
    public function get(array $columns = ['*']): Collection
    {
        return $this->query->get($columns);
    }

    public function first(array $columns = ['*']): ?EloquentAuditLog
    {
        /** @var EloquentAuditLog|null $log */
        $log = $this->query->first($columns);

        return $log;
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query->paginate($perPage, $columns);
    }
}
