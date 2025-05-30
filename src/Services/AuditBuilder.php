<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;

/**
 * A fluent builder for creating custom audit logs for a model.
 */
final class AuditBuilder
{
    private Model $model;

    private string $action;

    private array $oldValues = [];

    private array $newValues = [];

    private array $metadata = [];

    /**
     * Create a new AuditBuilder instance.
     *
     * @param  Model  $model  The model to audit
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->action = 'custom';
    }

    /**
     * Set the custom action name for the audit log.
     *
     * @param  string  $action  The action name
     */
    public function custom(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Set the old values for the audit log.
     *
     * @param  array  $values  The old values
     */
    public function from(array $values): self
    {
        $this->oldValues = $values;

        return $this;
    }

    /**
     * Set the new values for the audit log.
     *
     * @param  array  $values  The new values
     */
    public function to(array $values): self
    {
        $this->newValues = $values;

        return $this;
    }

    /**
     * Add custom metadata to the audit log.
     *
     * @param  array  $metadata  Additional metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Dispatch the audit event to log the custom action.
     */
    public function log(): void
    {
        // Merge model metadata with custom metadata
        $metadata = array_merge($this->model->getAuditMetadata(), $this->metadata);

        // If the model has getAuditableAttributes method, filter values
        if (method_exists($this->model, 'getAuditableAttributes')) {
            $this->oldValues = $this->model->getAuditableAttributes($this->oldValues);
            $this->newValues = $this->model->getAuditableAttributes($this->newValues);
        }

        Event::dispatch(new ModelAudited(
            model: $this->model,
            action: $this->action,
            oldValues: $this->oldValues,
            newValues: $this->newValues
        ));
    }
}
