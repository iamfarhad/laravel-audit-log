<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditTimeline;
use Illuminate\Console\Command;

final class AuditDiffCommand extends Command
{
    protected $signature = 'audit:diff {entity : Audited model class} {auditId : Audit log id}';

    protected $description = 'Show field-level changes for an audit log';

    public function handle(AuditTimeline $timeline): int
    {
        $entity = (string) $this->argument('entity');
        $auditId = (string) $this->argument('auditId');

        $log = EloquentAuditLog::forEntity($entity)->newQuery()->whereKey($auditId)->first();

        if (! $log instanceof EloquentAuditLog) {
            $this->error('Audit log not found.');

            return self::FAILURE;
        }

        $changes = $timeline->diff($log);

        if ($changes === []) {
            $this->info('No field changes found.');

            return self::SUCCESS;
        }

        $this->table(['Field', 'Old', 'New'], array_map(fn (array $change): array => [
            $change['field'],
            is_scalar($change['old']) || $change['old'] === null ? (string) $change['old'] : json_encode($change['old']),
            is_scalar($change['new']) || $change['new'] === null ? (string) $change['new'] : json_encode($change['new']),
        ], $changes));

        return self::SUCCESS;
    }
}
