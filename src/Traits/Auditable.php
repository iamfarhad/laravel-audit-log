<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

/**
 * Trait that implements the AuditableInterface to make models auditable.
 */
trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if ($model->isAuditingEnabled()) {
                Log::debug('Dispatching ModelAudited', [
                    'model' => get_class($model),
                    'action' => 'created',
                ]);
                event(new ModelAudited($model, 'created', null, $model->getAttributes()));
            }
        });

        static::updated(function (Model $model) {
            if ($model->isAuditingEnabled()) {
                $oldValues = $model->getOriginal();
                $newValues = $model->getChanges();

                $oldValues = array_intersect_key($oldValues, $newValues);

                if (! empty($newValues)) {
                    Log::debug('Dispatching ModelAudited', [
                        'model' => get_class($model),
                        'action' => 'updated',
                    ]);
                    event(new ModelAudited($model, 'updated', $oldValues, $newValues));
                }
            }
        });

        static::deleted(function (Model $model) {
            if ($model->isAuditingEnabled()) {
                Log::debug('Dispatching ModelAudited', [
                    'model' => get_class($model),
                    'action' => 'deleted',
                ]);
                event(new ModelAudited($model, 'deleted', $model->getOriginal(), null));
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if ($model->isAuditingEnabled()) {
                    Log::debug('Dispatching ModelAudited', [
                        'model' => get_class($model),
                        'action' => 'restored',
                    ]);
                    event(new ModelAudited($model, 'restored', null, $model->getAttributes()));
                }
            });
        }
    }

    /**
     * Determine if auditing is enabled for this model.
     */
    public function isAuditingEnabled(): bool
    {
        if (! property_exists($this, 'auditingEnabled')) {
            return true;
        }

        return $this->auditingEnabled;
    }

    /**
     * Enable auditing for this model instance.
     */
    public function enableAuditing(): self
    {
        $this->auditingEnabled = true;

        return $this;
    }

    /**
     * Disable auditing for this model instance.
     */
    public function disableAuditing(): self
    {
        $this->auditingEnabled = false;

        return $this;
    }

    /**
     * Get the entity type for audit logging.
     */
    public function getAuditEntityType(): string
    {
        return static::class;
    }

    /**
     * Get custom metadata for audit logs.
     */
    public function getAuditMetadata(): array
    {
        return [];
    }

    /**
     * Get the auditable attributes.
     */
    public function getAuditableAttributes(array $attributes): array
    {
        // Get exclude fields - combining model property and global config
        $exclude = config('audit-logger.fields.exclude', []);

        if (property_exists($this, 'auditExclude')) {
            $exclude = array_merge($exclude, $this->auditExclude);
        }

        $include = property_exists($this, 'auditInclude') ? $this->auditInclude : ['*'];

        // If include is ['*'], include all except excluded
        if ($include === ['*']) {
            return array_diff_key($attributes, array_flip($exclude));
        }

        // Otherwise, include only specified fields minus excluded
        $included = array_intersect_key($attributes, array_flip($include));

        return array_diff_key($included, array_flip($exclude));
    }

    /**
     * Get the primary key value for the model.
     * This method is already provided by Eloquent Model, but we need to ensure
     * it's available in the interface contract.
     */
    public function getKey(): string|int
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function auditLogs()
    {
        return $this->morphMany(EloquentAuditLog::forEntity(static::class), 'auditable');
    }
}
