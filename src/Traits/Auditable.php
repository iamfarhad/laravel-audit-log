<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Traits;

use App\Models\User;
use iamfarhad\LaravelAuditLog\Contracts\AuditableInterface;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    /**
     * Fields to exclude from audit logging.
     *
     * @var array<string>
     */
    protected array $auditExclude = [];

    /**
     * Fields to include in audit logging.
     *
     * @var array<string>
     */
    protected array $auditInclude = ['*'];

    /**
     * Whether auditing is enabled for this model.
     */
    protected bool $auditingEnabled = true;

    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if ($model instanceof AuditableInterface && $model->isAuditingEnabled()) {
                Log::debug("Dispatching ModelAudited", [
                    'model' => get_class($model),
                    'action' => 'created'
                ]);
                event(new ModelAudited($model, 'created', null, $model->getAttributes()));
            }
        });

        static::updated(function (Model $model) {
            if ($model instanceof AuditableInterface && $model->isAuditingEnabled()) {
                $oldValues = $model->getOriginal();
                $newValues = $model->getChanges();

                $oldValues = array_intersect_key($oldValues, $newValues);

                if (!empty($newValues)) {
                    Log::debug("Dispatching ModelAudited", [
                        'model' => get_class($model),
                        'action' => 'updated'
                    ]);
                    event(new ModelAudited($model, 'updated', $oldValues, $newValues));
                }
            }
        });

        static::deleted(function (Model $model) {
            if ($model instanceof AuditableInterface && $model->isAuditingEnabled()) {
                Log::debug("Dispatching ModelAudited", [
                    'model' => get_class($model),
                    'action' => 'deleted'
                ]);
                event(new ModelAudited($model, 'deleted', $model->getOriginal(), null));
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if ($model instanceof AuditableInterface && $model->isAuditingEnabled()) {
                    Log::debug("Dispatching ModelAudited", [
                        'model' => get_class($model),
                        'action' => 'restored'
                    ]);
                    event(new ModelAudited($model, 'restored', null, $model->getAttributes()));
                }
            });
        }
    }

    /**
     * Get fields to exclude from audit logging.
     *
     * @return array<string>
     */
    public function getAuditExclude(): array
    {
        return array_merge(
            $this->auditExclude,
            config('audit-logger.fields.exclude', [])
        );
    }

    /**
     * Get fields to include in audit logging.
     *
     * @return array<string>
     */
    public function getAuditInclude(): array
    {
        return $this->auditInclude;
    }

    /**
     * Determine if auditing is enabled for this model.
     */
    public function isAuditingEnabled(): bool
    {
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
        $include = $this->getAuditInclude();
        $exclude = $this->getAuditExclude();

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
}
