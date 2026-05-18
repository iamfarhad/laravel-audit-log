<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Services\AuditConfigValidator;
use iamfarhad\LaravelAuditLog\Services\AuditTableNameResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class AuditDoctorCommand extends Command
{
    protected $signature = 'audit:doctor {--entity= : Check one audited entity class}';

    protected $description = 'Run audit logger health checks';

    public function handle(AuditConfigValidator $validator, AuditTableNameResolver $tables): int
    {
        $result = $validator->validate();
        $failed = false;

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
            $failed = true;
        }

        $entities = $this->option('entity')
            ? [(string) $this->option('entity') => []]
            : (array) config('audit-logger.entities', []);

        foreach (array_keys($entities) as $entityClass) {
            if (! class_exists($entityClass)) {
                $this->error("Configured entity [{$entityClass}] does not exist.");
                $failed = true;
                continue;
            }

            $table = $tables->resolve($entityClass);
            $exists = Schema::connection(config('database.default'))->hasTable($table);
            $exists ? $this->info("OK: {$table} exists for {$entityClass}") : $this->warn("Missing audit table [{$table}] for {$entityClass}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
