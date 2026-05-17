<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Traits;

use Carbon\Carbon;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditBuilder;
use iamfarhad\LaravelAuditLog\Services\AuditFieldTransformer;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Services\AuditQuery;
use iamfarhad\LaravelAuditLog\Services\AuditRestorer;
use iamfarhad\LaravelAuditLog\Services\AuditTimeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if (! $model->isAuditingEnabled()) {
                return;
            }
            $auditLogger = app(AuditLogger::class);
            $causer = app(CauserResolverInterface::class)->resolve();
            $auditLogger->log(new AuditLog(
                entityType: $model->getAuditEntityType(),
                entityId: $model->getKey(),
                action: 'created',
                oldValues: null,
                newValues: $model->getAuditableAttributes($model->getAttributes(), 'new'),
                metadata: $model->getAuditMetadata(),
                causerType: $causer['type'],
                causerId: $causer['id'],
                createdAt: Carbon::now(),
                source: $auditLogger->getSource(),
            ));
        });

        static::updated(function (Model $model) {
            if (! $model->isAuditingEnabled()) {
                return;
            }
            $oldValues = $model->getAuditableAttributes($model->getOriginal(), 'old');
            $newValues = $model->getAuditableAttributes($model->getChanges(), 'new');
            $oldValues = array_intersect_key($oldValues, $newValues);

            if ($newValues === []) {
                return;
            }

            $auditLogger = app(AuditLogger::class);
            $causer = app(CauserResolverInterface::class)->resolve();
            $auditLogger->log(new AuditLog(
                entityType: $model->getAuditEntityType(),
                entityId: $model->getKey(),
                action: 'updated',
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: $model->getAuditMetadata(),
                causerType: $causer['type'],
                causerId: $causer['id'],
                createdAt: Carbon::now(),
                source: $auditLogger->getSource(),
            ));
        });

        static::deleted(function (Model $model) {
            if (! $model->isAuditingEnabled()) {
                return;
            }
            $auditLogger = app(AuditLogger::class);
            $causer = app(CauserResolverInterface::class)->resolve();
            $auditLogger->log(new AuditLog(
                entityType: $model->getAuditEntityType(),
                entityId: $model->getKey(),
                action: 'deleted',
                oldValues: $model->getAuditableAttributes($model->getOriginal(), 'old'),
                newValues: null,
                metadata: $model->getAuditMetadata(),
                causerType: $causer['type'],
                causerId: $causer['id'],
                createdAt: Carbon::now(),
                source: $auditLogger->getSource(),
            ));
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if (! $model->isAuditingEnabled()) {
                    return;
                }
                $auditLogger = app(AuditLogger::class);
                $causer = app(CauserResolverInterface::class)->resolve();
                $auditLogger->log(new AuditLog(
                    entityType: $model->getAuditEntityType(),
                    entityId: $model->getKey(),
                    action: 'restored',
                    oldValues: null,
                    newValues: $model->getAuditableAttributes($model->getAttributes(), 'new'),
                    metadata: $model->getAuditMetadata(),
                    causerType: $causer['type'],
                    causerId: $causer['id'],
                    createdAt: Carbon::now(),
                    source: $auditLogger->getSource(),
                ));
            });
        }
    }

    public function isAuditingEnabled(): bool
    {
        if (! (bool) config('audit-logger.enabled', true)) {
            return false;
        }

        return ! property_exists($this, 'auditingEnabled') || $this->auditingEnabled;
    }

    public function enableAuditing(): self
    {
        $this->auditingEnabled = true;

        return $this;
    }

    public function disableAuditing(): self
    {
        $this->auditingEnabled = false;

        return $this;
    }

    public function getAuditEntityType(): string
    {
        return static::class;
    }

    public function getAuditMetadata(): array
    {
        return [];
    }

    public function getAuditableAttributes(array $attributes, string $direction = 'new'): array
    {
        $exclude = config('audit-logger.fields.exclude', []);
        if (! (bool) config('audit-logger.fields.include_timestamps', true)) {
            $exclude = array_merge($exclude, ['created_at', 'updated_at', 'deleted_at']);
        }
        if (property_exists($this, 'auditExclude')) {
            $exclude = array_merge($exclude, $this->auditExclude);
        }
        $include = property_exists($this, 'auditInclude') ? $this->auditInclude : ['*'];
        $auditable = $include === ['*']
            ? array_diff_key($attributes, array_flip($exclude))
            : array_diff_key(array_intersect_key($attributes, array_flip($include)), array_flip($exclude));

        return app(AuditFieldTransformer::class)->transform($auditable, $this, $direction);
    }

    public function getKey(): string|int
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function auditLogs()
    {
        return EloquentAuditLog::forEntity(static::class)
            ->newQuery()
            ->where('entity_id', (string) $this->getKey());
    }

    public function auditHistory(): AuditQuery
    {
        return app(AuditLogger::class)->query(static::class)->forEntityId($this->getKey())->latest();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function auditTimeline(): Collection
    {
        return app(AuditTimeline::class)->forModel($this);
    }

    /** @return array<int, array{field: string, old: mixed, new: mixed}> */
    public function auditDiff(EloquentAuditLog|int|string|null $auditLog = null): array
    {
        $log = $auditLog instanceof EloquentAuditLog
            ? $auditLog
            : $this->auditLogs()
                ->when($auditLog !== null, fn ($query) => $query->whereKey($auditLog))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

        return $log instanceof EloquentAuditLog ? app(AuditTimeline::class)->diff($log) : [];
    }

    public function restoreFromAudit(EloquentAuditLog|int|string $auditLog, bool $save = true): self
    {
        app(AuditRestorer::class)->restore($this, $auditLog, $save);

        return $this;
    }

    public function rollbackToAudit(EloquentAuditLog|int|string $auditLog, bool $save = true): self
    {
        app(AuditRestorer::class)->rollback($this, $auditLog, $save);

        return $this;
    }

    /** @return array<int, array{field: string, current: mixed, target: mixed}> */
    public function previewRestore(EloquentAuditLog|int|string $auditLog, string $mode = 'restore'): array
    {
        return app(AuditRestorer::class)->preview($this, $auditLog, $mode);
    }

    public function audit(): AuditBuilder
    {
        return new AuditBuilder($this);
    }

    public function getAuditRetentionConfig(): array
    {
        return property_exists($this, 'auditRetention') && is_array($this->auditRetention) ? $this->auditRetention : [];
    }

    public function isRetentionEnabled(): bool
    {
        $config = $this->getAuditRetentionConfig();

        return isset($config['enabled']) ? (bool) $config['enabled'] : config('audit-logger.retention.enabled', false);
    }

    public function getRetentionStrategy(): string
    {
        $config = $this->getAuditRetentionConfig();

        return $config['strategy'] ?? config('audit-logger.retention.strategy', 'delete');
    }

    public function getRetentionDays(): int
    {
        $config = $this->getAuditRetentionConfig();

        return $config['days'] ?? config('audit-logger.retention.days', 365);
    }

    public function getAnonymizeDays(): int
    {
        $config = $this->getAuditRetentionConfig();

        return $config['anonymize_after_days'] ?? config('audit-logger.retention.anonymize_after_days', 180);
    }
}
