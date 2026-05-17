<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EloquentAuditLog extends Model
{
    private static ?array $configCache = null;

    public $timestamps = false;

    protected $fillable = [
        'entity_id',
        'action',
        'old_values',
        'new_values',
        'causer_type',
        'causer_id',
        'metadata',
        'created_at',
        'source',
        'audit_hash',
        'previous_hash',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        $config = self::getConfigCache();
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
        return $query->where(function (Builder $query) use ($startDate, $endDate) {
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

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('entity_id', 'like', $like)
                ->orWhere('action', 'like', $like)
                ->orWhere('source', 'like', $like)
                ->orWhere('causer_type', 'like', $like)
                ->orWhere('causer_id', 'like', $like)
                ->orWhere('old_values', 'like', $like)
                ->orWhere('new_values', 'like', $like)
                ->orWhere('metadata', 'like', $like);
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
        return $query->where(function (Builder $query) {
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

        return $query->where(function (Builder $query) {
            $query->where('source', 'like', 'App\\Http\\Controllers\\%')
                ->orWhere('source', 'like', 'App\\\\Http\\\\Controllers\\\\%');
        });
    }

    private static function getConfigCache(): array
    {
        if (self::$configCache === null) {
            self::$configCache = config('audit-logger');
        }

        return self::$configCache;
    }

    public static function forEntity(string $entityClass): static
    {
        $config = self::getConfigCache();
        $driverName = $config['default'] ?? 'mysql';
        $driverConfig = $config['drivers'][$driverName] ?? $config['drivers']['mysql'] ?? [];
        $entityConfig = $config['entities'][$entityClass] ?? [];
        $className = Str::snake(class_basename($entityClass));
        $tableName = $entityConfig['audit_table'] ?? $entityConfig['table'] ?? Str::plural($className);
        $tablePrefix = $driverConfig['table_prefix'] ?? 'audit_';
        $tableSuffix = $driverConfig['table_suffix'] ?? '_logs';

        if (! str_starts_with($tableName, $tablePrefix)) {
            $tableName = "{$tablePrefix}{$tableName}";
        }

        if (! str_ends_with($tableName, $tableSuffix)) {
            $tableName = "{$tableName}{$tableSuffix}";
        }

        $instance = new self;
        $instance->setTable($tableName);

        $connection = $driverConfig['connection'] ?? config('database.default');
        if ($connection) {
            $instance->setConnection($connection);
        }

        return $instance;
    }
}
