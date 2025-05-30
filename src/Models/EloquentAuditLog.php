<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

final class EloquentAuditLog extends Model
{
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
     * @var array<string>
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
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'metadata' => 'array',
    ];

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
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public static function forEntity(string $entityClass): static
    {
        $className = Str::snake(class_basename($entityClass));

        // Handle pluralization
        $tableName = Str::plural($className);

        $tablePrefix = config('audit-logger.drivers.mysql.table_prefix', 'audit_');
        $tableSuffix = config('audit-logger.drivers.mysql.table_suffix', '_logs');

        $table = "{$tablePrefix}{$tableName}{$tableSuffix}";

        return (new self)->setTable($table);
    }
}
