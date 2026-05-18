<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Services\AuditTableNameResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class AuditUpgradeCommand extends Command
{
    protected $signature = 'audit:upgrade {target=v1-to-v2} {--dry-run : Show required changes without applying them}';

    protected $description = 'Check or apply audit logger upgrade steps';

    public function handle(AuditTableNameResolver $tables): int
    {
        if ($this->argument('target') !== 'v1-to-v2') {
            $this->error('Only v1-to-v2 upgrade checks are supported.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $entities = array_keys((array) config('audit-logger.entities', []));

        foreach ($entities as $entityClass) {
            $tableName = $tables->resolve($entityClass);

            if (! Schema::hasTable($tableName)) {
                $this->warn("Missing audit table [{$tableName}].");
                continue;
            }

            $missing = array_values(array_filter([
                'audit_hash',
                'previous_hash',
                'tenant_type',
                'tenant_id',
                'changes',
            ], fn (string $column): bool => ! Schema::hasColumn($tableName, $column)));

            if ($missing === []) {
                $this->info("{$tableName}: already v2-ready.");
                continue;
            }

            $this->line("{$tableName}: missing ".implode(', ', $missing));
        }

        if ($dryRun) {
            $this->info('Dry run complete. Generate migrations with audit:make-migration or add missing columns manually.');
        }

        return self::SUCCESS;
    }
}
