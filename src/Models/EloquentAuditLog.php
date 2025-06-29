<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EloquentAuditLog extends Model
{
    /**
     * Cache for configuration values to avoid repeated config() calls.
     */
    private static ?array $configCache = null;

    /**
     * Indicates if the model should be timestamped.
     * We handle the created_at timestamp manually.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'metadata' => 'array',
    ];

    /**
     * Get the connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        $config = self::getConfigCache();

        return $config['drivers']['mysql']['connection'] ?? config('database.default');
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
        return $query->where('entity_type', $entityClass);
    }

    public function scopeForEntityId(Builder $query, $entityId): Builder
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeForAction(Builder $query, $action): Builder
    {
        return $query->where('action', $action);
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

    public function scopeFromConsole(Builder $query): Builder
    {
        /** @var Builder $query */
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
            // Escape the controller string to prevent SQL injection
            $escapedController = str_replace(['%', '_'], ['\\%', '\\_'], $controller);

            return $query->where('source', 'like', "%{$escapedController}%");
        }

        return $query->where(function (Builder $query) {
            $query->where('source', 'like', 'App\\Http\\Controllers\\%')
                ->orWhere('source', 'like', 'App\\\\Http\\\\Controllers\\\\%');
        });
    }

    /**
     * Get cached configuration to avoid repeated config() calls.
     */
    private static function getConfigCache(): array
    {
        if (self::$configCache === null) {
            self::$configCache = config('audit-logger');
        }

        return self::$configCache;
    }

    public static function forEntity(string $entityClass): static
    {
        $className = Str::snake(class_basename($entityClass));

        // Handle pluralization
        $tableName = Str::plural($className);

        $config = self::getConfigCache();
        $tablePrefix = $config['drivers']['mysql']['table_prefix'] ?? 'audit_';
        $tableSuffix = $config['drivers']['mysql']['table_suffix'] ?? '_logs';

        $table = "{$tablePrefix}{$tableName}{$tableSuffix}";

        $instance = new self;
        $instance->setTable($table);

        // Set the connection from config
        $connection = $config['drivers']['mysql']['connection'] ?? config('database.default');
        if ($connection) {
            $instance->setConnection($connection);
        }

        return $instance;
    }
}
