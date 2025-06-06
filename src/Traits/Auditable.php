<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Services\AuditBuilder;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

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
                app(AuditLogger::class)->log(new AuditLog(
                    entityType: $model->getAuditEntityType(),
                    entityId: $model->getKey(),
                    action: 'created',
                    oldValues: null,
                    newValues: $model->getAttributes(),
                    metadata: $model->getAuditMetadata(),
                    causerType: app(CauserResolverInterface::class)->resolve()['type'],
                    causerId: app(CauserResolverInterface::class)->resolve()['id'],
                    createdAt: Carbon::now(),
                    source: app(AuditLogger::class)->getSource(),
                ));
            }
        });

        static::updated(function (Model $model) {
            if ($model->isAuditingEnabled()) {
                $oldValues = $model->getOriginal();
                $newValues = $model->getChanges();

                $oldValues = array_intersect_key($oldValues, $newValues);

                if (! empty($newValues)) {
                    app(AuditLogger::class)->log(new AuditLog(
                        entityType: $model->getAuditEntityType(),
                        entityId: $model->getKey(),
                        action: 'updated',
                        oldValues: $oldValues,
                        newValues: $newValues,
                        metadata: $model->getAuditMetadata(),
                        causerType: app(CauserResolverInterface::class)->resolve()['type'],
                        causerId: app(CauserResolverInterface::class)->resolve()['id'],
                        createdAt: Carbon::now(),
                        source: app(AuditLogger::class)->getSource(),
                    ));
                }
            }
        });

        static::deleted(function (Model $model) {
            if ($model->isAuditingEnabled()) {
                app(AuditLogger::class)->log(new AuditLog(
                    entityType: $model->getAuditEntityType(),
                    entityId: $model->getKey(),
                    action: 'deleted',
                    oldValues: $model->getOriginal(),
                    newValues: null,
                    metadata: $model->getAuditMetadata(),
                    causerType: app(CauserResolverInterface::class)->resolve()['type'],
                    causerId: app(CauserResolverInterface::class)->resolve()['id'],
                    createdAt: Carbon::now(),
                    source: app(AuditLogger::class)->getSource(),
                ));
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if ($model->isAuditingEnabled()) {
                    app(AuditLogger::class)->log(new AuditLog(
                        entityType: $model->getAuditEntityType(),
                        entityId: $model->getKey(),
                        action: 'restored',
                        oldValues: null,
                        newValues: $model->getAttributes(),
                        metadata: $model->getAuditMetadata(),
                        causerType: app(CauserResolverInterface::class)->resolve()['type'],
                        causerId: app(CauserResolverInterface::class)->resolve()['id'],
                        createdAt: Carbon::now(),
                        source: app(AuditLogger::class)->getSource(),
                    ));
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

    /**
     * Initiate a fluent builder for custom audit logging.
     */
    public function audit(): AuditBuilder
    {
        return new AuditBuilder($this);
    }
}
