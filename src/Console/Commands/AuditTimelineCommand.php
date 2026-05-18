<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use Illuminate\Console\Command;

final class AuditTimelineCommand extends Command
{
    protected $signature = 'audit:timeline {entity : Audited model class} {entityId : Entity id}';

    protected $description = 'Show an audit timeline for an entity instance';

    public function handle(): int
    {
        $entity = (string) $this->argument('entity');
        $entityId = (string) $this->argument('entityId');

        $logs = EloquentAuditLog::forEntity($entity)
            ->newQuery()
            ->where('entity_id', $entityId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'action', 'causer_type', 'causer_id', 'source', 'created_at']);

        if ($logs->isEmpty()) {
            $this->warn('No audit logs found.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Action', 'Causer', 'Source', 'Created At'], $logs->map(fn ($log): array => [
            $log->getKey(),
            $log->action,
            trim(($log->causer_type ?? '').'#'.($log->causer_id ?? ''), '#'),
            $log->source,
            (string) $log->created_at,
        ])->all());

        return self::SUCCESS;
    }
}
