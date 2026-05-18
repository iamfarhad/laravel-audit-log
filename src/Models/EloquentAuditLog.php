<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EloquentAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entity_id',
        'action',
        'old_values',
        'new_values',
        'changes',
        'causer_type',
        'causer_id',
        'tenant_type',
        'tenant_id',
        'metadata',
        'created_at',
        'source',
        'audit_hash',
        'previous_hash',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'changes' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        if (! (bool) config('audit-logger.security.append_only', false)) {
            return;
        }

        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    public function getConnectionName(): ?string
    {
        $config = config('audit-logger');
        $driverName = $config['default'] ?? 'mysql';

        return $config['drivers'][$driverName]['connection'] ?? config('database.default');
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function causer()
    {
        return $this->morphTo();
    }

    public function tenant()
    {
        return $this->morphTo(__FUNCTION__, 'tenant_type', 'tenant_id');
    }

    public function scopeForEntity(Builder $query, $entityClass): Builder
    {
        return $query;
    }

    public function scopeForEntityId(Builder $query, $entityId): Builder
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeForAction(Builder $query, $action): Builder
    {
        return is_array($action) ? $query->whereIn('action', $action) : $query->where('action', $action);
    }

    public function scopeForTenant(Builder $query, string|int $tenantId, ?string $tenantType = null): Builder
    {
        $query->where('tenant_id', (string) $tenantId);

        if ($tenantType !== null) {
            $query->where('tenant_type', $tenantType);
        }

        return $query;
    }

    public function scopeForCauser(Builder $query, $causerClass): Builder
    {
        return $query->where('causer_type', $causerClass);
    }

    public function scopeForCauserId(Builder $query, $causerId): Builder
    {
        return $query->where('causer_id', $causerId);
    }

    public function scopeForCreatedAt(Builder $query, $createdAt): Builder
    {
        return $query->where('created_at', $createdAt);
    }

    public function scopeDateGreaterThan(Builder $query, $date): Builder
    {
        return $query->where('created_at', '>', $date);
    }

    public function scopeDateLessThan(Builder $query, $date): Builder
    {
        return $query->where('created_at', '<', $date);
    }

    public function scopeDateBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->where(function (Builder $query) use ($startDate, $endDate): void {
            $query->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate);
        });
    }

    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $jsonColumns = ['old_values', 'new_values', 'metadata'];
        $connection = $query->getModel()->getConnection();
        $grammar = $connection->getQueryGrammar();
        $isPostgreSQL = $connection->getDriverName() === 'pgsql';

        return $query->where(function (Builder $query) use ($grammar, $isPostgreSQL, $jsonColumns, $like): void {
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
    }

    public function scopeFromConsole(Builder $query): Builder
    {
        $query->whereNotNull('source');
        $query->where('source', 'not like', 'App\\Http\\Controllers\\%');
        $query->where('source', 'not like', 'App\\\\Http\\\\Controllers\\\\%');

        return $query;
    }

    public function scopeFromHttp(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('source', 'like', 'App\\Http\\Controllers\\%')
                ->orWhere('source', 'like', 'App\\\\Http\\\\Controllers\\\\%')
                ->orWhere('source', '=', 'http');
        });
    }

    public function scopeFromCommand(Builder $query, string $command): Builder
    {
        return $query->where('source', $command);
    }

    public function scopeFromController(Builder $query, ?string $controller = null): Builder
    {
        if ($controller !== null && $controller !== '') {
            $escapedController = str_replace(['%', '_'], ['\\%', '\\_'], $controller);

            return $query->where('source', 'like', "%{$escapedController}%");
        }

        return $query->where(function (Builder $query): void {
            $query->where('source', 'like', 'App\\Http\\Controllers\\%')
                ->orWhere('source', 'like', 'App\\\\Http\\\\Controllers\\\\%');
        });
    }

    public static function forEntity(string $entityClass): static
    {
        $instance = new self;
        $instance->setTable(app(\iamfarhad\LaravelAuditLog\Services\AuditTableNameResolver::class)->resolve($entityClass));

        $config = config('audit-logger');
        $driverName = $config['default'] ?? 'mysql';
        $driverConfig = $config['drivers'][$driverName] ?? $config['drivers']['mysql'] ?? [];
        $connection = $driverConfig['connection'] ?? config('database.default');
        if ($connection) {
            $instance->setConnection($connection);
        }

        return $instance;
    }
}
