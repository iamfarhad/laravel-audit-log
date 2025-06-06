<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

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
        // Merge model metadata with custom metadata if available
        $modelMetadata = method_exists($this->model, 'getAuditMetadata')
            ? $this->model->getAuditMetadata()
            : [];

        $metadata = array_merge($modelMetadata, $this->metadata);

        // If the model has getAuditableAttributes method, filter values
        if (method_exists($this->model, 'getAuditableAttributes')) {
            $this->oldValues = $this->model->getAuditableAttributes($this->oldValues);
            $this->newValues = $this->model->getAuditableAttributes($this->newValues);
        }

        // Ensure the model has the getAuditEntityType method
        $entityType = method_exists($this->model, 'getAuditEntityType')
            ? $this->model->getAuditEntityType()
            : get_class($this->model);

        app(AuditLogger::class)->log(new AuditLog(
            entityType: $entityType,
            entityId: $this->model->getKey(),
            action: $this->action,
            oldValues: $this->oldValues,
            newValues: $this->newValues,
            causerType: app(CauserResolverInterface::class)->resolve()['type'],
            causerId: app(CauserResolverInterface::class)->resolve()['id'],
            metadata: $metadata,
            createdAt: Carbon::now(),
            source: $this->getSource()
        ));
    }

    /**
     * Get the source of the audit event.
     */
    private function getSource(): ?string
    {
        if (App::runningInConsole()) {
            // Try to get the command from $_SERVER['argv']
            $argv = $_SERVER['argv'] ?? [];

            // Look for artisan command (usually at index 1, but could be at index 2 if using 'php artisan')
            foreach ($argv as $index => $arg) {
                if (str_starts_with($arg, 'app:') || str_starts_with($arg, 'make:') || str_starts_with($arg, 'migrate') || str_contains($arg, ':')) {
                    return $arg;
                }
            }

            // Fallback to check if we have any argv[1] that looks like a command
            if (isset($argv[1]) && ! str_contains($argv[1], '/') && ! str_contains($argv[1], '.php')) {
                return $argv[1];
            }

            return 'console';
        }

        $route = Request::route();
        if ($route !== null && is_object($route) && method_exists($route, 'getActionName')) {
            $controller = $route->getActionName();

            return is_string($controller) ? $controller : 'http';
        }

        return null;
    }
}
